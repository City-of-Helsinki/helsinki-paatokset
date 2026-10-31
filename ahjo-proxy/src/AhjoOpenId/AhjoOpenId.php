<?php

declare(strict_types=1);

namespace App\AhjoOpenId;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Handler for Ahjo API OpenID connector.
 */
final readonly class AhjoOpenId {

  private const string LOCK_NAME = 'ahjo-auth';

  public function __construct(
    private Settings $settings,
    private TokenStorage $storage,
    private HttpClientInterface $httpClient,
    private LoggerInterface $logger,
  ) {
  }

  /**
   * Gets URL that starts the OpenID authorization code flow.
   *
   * @param string $state
   *   OpenID state.
   *
   * @todo Add PKCE if Ahjo supports it. The code verifier can be derived
   *   from the state with HMAC, so the proxy stays stateless.
   *
   * @see \App\AhjoOpenId\StateSigner
   *
   * @throws \App\AhjoOpenId\AhjoOpenIdException
   */
  public function getAuthUrl(string $state): string {
    $this->settings->assertValid();

    return sprintf('%s?%s', $this->settings->authUrl, http_build_query([
      'client_id' => $this->settings->clientId,
      'scope' => $this->settings->scope,
      'response_type' => 'code',
      'redirect_uri' => $this->settings->redirectUri,
      'state' => $state,
    ]));
  }

  /**
   * Refresh auth token.
   *
   * @param null|string $code
   *   OpenID flow code. If not provided, the refresh token is used.
   *
   * @throws \App\AhjoOpenId\AhjoOpenIdException
   */
  public function refreshAuthToken(?string $code = NULL): AhjoAuthToken {
    $this->logger->info('Refreshing ahjo auth token');

    if ($code) {
      // Refresh token with auth code grant.
      return $this->makeTokenRequest([
        'client_id' => $this->settings->clientId,
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => $this->settings->redirectUri,
      ]);
    }

    try {
      $token = $this->storage->getToken();
    }
    catch (\InvalidArgumentException) {
      throw new AhjoOpenIdException('Missing refresh token');
    }

    // Refresh with refresh token grant.
    return $this->makeTokenRequest([
      'client_id' => $this->settings->clientId,
      'grant_type' => 'refresh_token',
      'refresh_token' => $token->refreshToken,
    ]);
  }

  /**
   * Gets the access token.
   *
   * Warning: this does not check if the token is still valid.
   *
   * @return string|null
   *   The access token or NULL if token is missing.
   */
  public function getAuthToken(): ?string {
    try {
      $token = $this->storage->getToken();
    }
    catch (\InvalidArgumentException $e) {
      $this->logger->error('Ahjo auth token is missing: {message}', ['message' => $e->getMessage()]);
      return NULL;
    }

    if ($token->isExpired()) {
      $this->logger->error('Ahjo auth token expired');
    }

    return $token->token;
  }

  /**
   * Gets token expiration timestamp or NULL if the token is missing.
   */
  public function getAuthTokenExpiration(): ?int {
    try {
      return $this->storage->getToken()->expires;
    }
    catch (\InvalidArgumentException) {
      return NULL;
    }
  }

  /**
   * Make OpenID token request.
   *
   * @param array<string, string> $formParameters
   *   Request parameters.
   *
   * @throws \App\AhjoOpenId\AhjoOpenIdException
   */
  private function makeTokenRequest(array $formParameters): AhjoAuthToken {
    $this->settings->assertValid();

    // Refresh tokens are invalidated the moment they are used.
    // It is critical that only one refresh attempt is made.
    $lock = $this->storage->createLock(self::LOCK_NAME);
    if (!$lock->acquire()) {
      throw new AhjoOpenIdException('Failed to acquire lock');
    }

    try {
      $this->storage->clearToken();

      $response = $this->httpClient->request('POST', $this->settings->tokenUrl, [
        'auth_basic' => [$this->settings->clientId, $this->settings->clientSecret],
        'body' => $formParameters,
      ]);

      $body = $response->getContent();
      try {
        $data = json_decode($body, TRUE, flags: JSON_THROW_ON_ERROR);
        $token = AhjoAuthToken::fromAhjoResponse(is_array($data) ? $data : []);
      }
      catch (\InvalidArgumentException | \JsonException $e) {
        throw new AhjoOpenIdException('Invalid token response: ' . $body, previous: $e);
      }

      $this->storage->setToken($token);

      return $token;
    }
    catch (ExceptionInterface $e) {
      throw new AhjoOpenIdException($e->getMessage(), previous: $e);
    }
    finally {
      $lock->release();
    }
  }

}
