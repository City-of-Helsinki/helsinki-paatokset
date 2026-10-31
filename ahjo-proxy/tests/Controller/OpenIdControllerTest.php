<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\AhjoOpenId\TokenStorage;
use App\Tests\AuthTestTrait;
use App\Tests\MockResponseFactory;
use App\Tests\StorageTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests the OpenID controller.
 */
final class OpenIdControllerTest extends WebTestCase {

  use AuthTestTrait;
  use StorageTestTrait {
    setUp as storageSetUp;
  }

  /**
   * The browser.
   */
  private KernelBrowser $client;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->storageSetUp();
    self::setUpOidc();
    $this->client = self::createClient();
  }

  /**
   * Makes an authenticated request to the OpenID endpoint.
   *
   * @param string $method
   *   Request method.
   * @param string|null $accessToken
   *   Access token. Defaults to a valid token.
   * @param string $apiKey
   *   Api key.
   */
  private function request(string $method, ?string $accessToken = NULL, string $apiKey = 'test-api-key'): Response {
    $this->client->request($method, '/openid', server: [
      'HTTP_API_KEY' => $apiKey,
      'HTTP_X_ACCESS_TOKEN' => $accessToken ?? self::createAccessToken(),
    ]);

    $response = $this->client->getResponse();
    if (!$response->isClientError()) {
      $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
    }

    return $response;
  }

  /**
   * Gets the token status.
   *
   * @return array<mixed>
   *   Decoded JSON response.
   */
  private function getStatus(?string $accessToken = NULL, string $apiKey = 'test-api-key'): array {
    $response = $this->request('GET', $accessToken, $apiKey);
    $this->assertSame('application/json', $response->headers->get('Content-Type'));

    $data = json_decode((string) $response->getContent(), TRUE, flags: JSON_THROW_ON_ERROR);
    $this->assertIsArray($data);

    return $data;
  }

  /**
   * Starts the flow and gets the auth URL.
   */
  private function startFlow(?string $accessToken = NULL, string $apiKey = 'test-api-key'): ?string {
    return $this->request('POST', $accessToken, $apiKey)->headers->get('Location');
  }

  /**
   * Starts the flow and gets the state from the auth URL.
   */
  private function getState(): string {
    $authUrl = $this->startFlow();
    $this->assertIsString($authUrl);
    parse_str((string) parse_url($authUrl, PHP_URL_QUERY), $query);
    $this->assertIsString($query['state']);

    return $query['state'];
  }

  /**
   * Calls the callback endpoint like a browser redirected from Ahjo.
   *
   * @param array<string, string> $query
   *   Query parameters.
   *
   * @return string
   *   Response body.
   */
  private function ahjoRedirect(array $query): string {
    // The browser has no proxy credentials.
    $this->client->request('GET', '/openid/callback?' . http_build_query($query));

    $response = $this->client->getResponse();
    $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
    $this->assertSame('no-referrer', $response->headers->get('Referrer-Policy'));

    return (string) $response->getContent();
  }

  /**
   * Tests the OpenID flow.
   */
  public function testOpenIdFlow(): void {
    MockResponseFactory::setResponse(new MockResponse(json_encode([
      'access_token' => 'new-access',
      'refresh_token' => 'new-refresh',
      'expires_in' => 3600,
    ], JSON_THROW_ON_ERROR)));

    $this->assertSame(['expires' => NULL], $this->getStatus());
    $this->assertResponseIsSuccessful();

    $authUrl = $this->startFlow();
    $this->assertResponseRedirects();
    $this->assertIsString($authUrl);
    $this->assertStringStartsWith('https://johdontyopoyta.hel.fi/ids4/connect/authorize?', $authUrl);

    parse_str((string) parse_url($authUrl, PHP_URL_QUERY), $query);
    $this->assertSame('https://ahjo-proxy.test/openid/callback', $query['redirect_uri']);
    $this->assertSame('paatoksetpalvelu', $query['client_id']);
    $this->assertIsString($query['state']);

    // Every flow gets a unique state.
    $this->assertNotSame($query['state'], $this->getState());

    $body = $this->ahjoRedirect(['code' => 'abc', 'state' => $query['state']]);
    $this->assertResponseIsSuccessful();
    $this->assertStringContainsString('Ahjo auth token stored', $body);
    $this->assertStringNotContainsString('new-access', $body);

    $this->assertCount(1, MockResponseFactory::$requests);
    ['method' => $method, 'url' => $url, 'options' => $options] = MockResponseFactory::$requests[0];
    $this->assertSame('POST', $method);
    $this->assertSame('https://johdontyopoyta.hel.fi/ids4/connect/token', $url);
    $this->assertIsArray($options['normalized_headers']);
    $this->assertSame(['Authorization: Basic ' . base64_encode('paatoksetpalvelu:test-secret')], $options['normalized_headers']['authorization']);
    $this->assertIsString($options['body']);
    parse_str($options['body'], $body);
    $this->assertSame('authorization_code', $body['grant_type']);
    $this->assertSame('abc', $body['code']);
    $this->assertSame('https://ahjo-proxy.test/openid/callback', $body['redirect_uri']);

    $this->assertSame('new-access', (new TokenStorage(self::getStorageDir()))->getToken()->token);

    $data = $this->getStatus();
    $this->assertIsInt($data['expires']);
    // The token itself is never returned.
    $this->assertStringNotContainsString('new-access', (string) $this->client->getResponse()->getContent());
  }

  /**
   * Tests that the callback rejects requests without a valid state.
   */
  public function testInvalidCallback(): void {
    $state = $this->getState();
    [$payload, $signature] = explode('.', $state);

    $forged = rtrim(strtr(base64_encode(json_encode([
      'sub' => 'attacker',
      'exp' => time() + 600,
      'n' => 'x',
    ], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');

    $invalidStates = [
      'missing' => NULL,
      'empty' => '',
      'garbage' => 'not-a-state',
      'tampered payload' => $forged . '.' . $signature,
      'tampered signature' => $payload . '.' . strrev($signature),
      'extra part' => $state . '.x',
      'too long' => str_repeat('a', 2000) . '.' . $signature,
    ];

    foreach ($invalidStates as $label => $invalidState) {
      $body = $this->ahjoRedirect(array_filter(['code' => 'abc', 'state' => $invalidState], static fn ($value) => $value !== NULL));
      $this->assertResponseStatusCodeSame(400, $label);
      $this->assertStringContainsString('Invalid or expired', $body, $label);
    }

    // Valid state without a code.
    foreach ([[], ['error' => 'access_denied']] as $query) {
      $this->ahjoRedirect(['state' => $state, ...$query]);
      $this->assertResponseStatusCodeSame(400);
    }

    $this->assertEmpty(MockResponseFactory::$requests);
  }

  /**
   * Tests that failed code exchange is not leaked to the browser.
   */
  public function testTokenRequestFailure(): void {
    $state = $this->getState();
    MockResponseFactory::setResponse(new MockResponse('{"error": "invalid_grant", "secret": "leak"}', ['http_code' => 400]));

    $body = $this->ahjoRedirect(['code' => 'abc', 'state' => $state]);
    $this->assertResponseStatusCodeSame(502);
    $this->assertStringNotContainsString('leak', $body);
  }

  /**
   * Tests that the callback output is escaped.
   */
  public function testCallbackEscaping(): void {
    $body = $this->ahjoRedirect(['code' => '<script>', 'state' => '<script>']);
    $this->assertStringNotContainsString('<script>', $body);
  }

  /**
   * Tests that unauthenticated requests never get a state.
   */
  public function testAuthentication(): void {
    self::setToken('stored-access');
    MockResponseFactory::setResponse(new MockResponse('{}'));

    $otherKey = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
    $this->assertInstanceOf(\OpenSSLAsymmetricKey::class, $otherKey);

    $invalidTokens = [
      'missing' => '',
      'garbage' => 'not-a-jwt',
      'wrong key' => self::createAccessToken(key: $otherKey),
      'expired' => self::createAccessToken(['exp' => time() - 60]),
      'no exp' => self::createAccessToken(['exp' => NULL]),
      'wrong issuer' => self::createAccessToken(['iss' => 'https://evil.test/auth/realms/helsinki-tunnistus']),
      'wrong client' => self::createAccessToken(['azp' => 'evil-client']),
      'id token' => self::createAccessToken(['typ' => 'ID']),
      'no groups' => self::createAccessToken(['ad_groups' => NULL]),
      'wrong groups' => self::createAccessToken(['ad_groups' => ['some-group']]),
    ];

    foreach ($invalidTokens as $label => $token) {
      $this->assertNull($this->startFlow(accessToken: $token), $label);
      $this->assertResponseStatusCodeSame(401, $label);
    }

    // Valid access token without the api key.
    $this->getStatus(apiKey: 'wrong');
    $this->assertResponseStatusCodeSame(401);
    $this->assertNull($this->startFlow(apiKey: 'wrong'));
    $this->assertResponseStatusCodeSame(401);

    // Status only requires the api key.
    $this->assertIsInt($this->getStatus(accessToken: '')['expires']);
    $this->assertResponseIsSuccessful();

    $this->assertEmpty(MockResponseFactory::$requests);
    $this->assertSame('stored-access', (new TokenStorage(self::getStorageDir()))->getToken()->token);

    // Another allowed client is accepted.
    $this->assertIsString($this->startFlow(accessToken: self::createAccessToken(['azp' => 'other-client'])));
  }

}
