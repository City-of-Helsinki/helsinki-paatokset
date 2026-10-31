<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\AhjoOpenId\TokenStorage;
use App\Tests\MockResponseFactory;
use App\Tests\StorageTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Tests the OpenID controller.
 */
final class OpenIdControllerTest extends WebTestCase {

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
    $this->client = self::createClient();
  }

  /**
   * Starts the auth flow and returns the state.
   */
  private function startAuthFlow(): string {
    $crawler = $this->client->request('GET', '/openid');
    $this->assertResponseIsSuccessful();

    $authUrl = (string) $crawler->filterXPath('//a')->attr('href');
    $this->assertStringStartsWith('https://johdontyopoyta.hel.fi/ids4/connect/authorize?', $authUrl);

    parse_str((string) parse_url($authUrl, PHP_URL_QUERY), $query);
    $this->assertSame('http://localhost/openid/callback', $query['redirect_uri']);
    $this->assertSame('paatoksetpalvelu', $query['client_id']);
    $this->assertIsString($query['state']);

    return $query['state'];
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

    $state = $this->startAuthFlow();
    $this->assertStringContainsString('Token has not been set.', (string) $this->client->getResponse()->getContent());

    // Invalid state.
    $this->client->request('GET', '/openid/callback', ['code' => 'abc', 'state' => 'wrong']);
    $this->assertResponseStatusCodeSame(400);

    // State is consumed by the failed attempt.
    $this->client->request('GET', '/openid/callback', ['code' => 'abc', 'state' => $state]);
    $this->assertResponseStatusCodeSame(400);

    // Missing code.
    $this->client->request('GET', '/openid/callback', ['state' => $this->startAuthFlow()]);
    $this->assertResponseStatusCodeSame(400);

    $this->assertEmpty(MockResponseFactory::$requests);

    $this->client->request('GET', '/openid/callback', ['code' => 'abc', 'state' => $this->startAuthFlow()]);
    $this->assertResponseIsSuccessful();
    $this->assertStringContainsString('Token successfully stored!', (string) $this->client->getResponse()->getContent());

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
    $this->assertSame('http://localhost/openid/callback', $body['redirect_uri']);

    $this->assertSame('new-access', (new TokenStorage(self::getStorageDir()))->getToken()->token);

    $this->client->request('GET', '/openid');
    $content = (string) $this->client->getResponse()->getContent();
    $this->assertStringContainsString('Token is valid until', $content);
    // The token itself is never shown.
    $this->assertStringNotContainsString('new-access', $content);
  }

}
