<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\AhjoOpenId;

use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Utility\Error;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\paatokset_ahjo_api\AhjoOpenId\DTO\AhjoAuthToken;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Handler for AHJO API Open ID connector.
 *
 * The token must be stored in a storage without a static cache,
 * so long-running processes always see the current token.
 */
class AhjoOpenId implements LoggerAwareInterface {

  use LoggerAwareTrait;

  public const string TOKEN_COLLECTION = 'paatokset_ahjo_api.auth_token';

  public function __construct(
    private readonly Settings $settings,
    private readonly ClientInterface $httpClient,
    private readonly KeyValueFactoryInterface $keyValueFactory,
    #[Autowire(service: 'lock')]
    private readonly LockBackendInterface $lock,
    private readonly EnvironmentResolverInterface $environmentResolver,
  ) {
  }

  /**
   * Check if connector is configured.
   *
   * @return bool
   *   FALSE if connector has missing configs.
   */
  public function isConfigured(): bool {
    try {
      // Missing config options.
      $this->settings->assertValid();

      $token = $this->getToken();

      // Missing refresh token.
      return !empty($token->refreshToken);
    }
    catch (AhjoOpenIdException | \InvalidArgumentException) {
    }

    return FALSE;
  }

  /**
   * Refresh AUTH token.
   *
   * @param null|string $code
   *   OpenID flow code. If not provided, the refresh token is used.
   *
   * @throws \Drupal\paatokset_ahjo_api\AhjoOpenId\AhjoOpenIdException
   */
  public function refreshAuthToken(?string $code = NULL): AhjoAuthToken {
    $this->logger?->info('Refreshing ahjo auth token');

    if ($code) {
      // Refresh token with auth code grant.
      return $this->makeTokenRequest([
        'client_id' => $this->settings->clientId,
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => $this->settings->callbackUrl,
      ]);
    }
    else {
      try {
        $token = $this->getToken();
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
  }

  /**
   * Gets the key of the token inside the keyvalue collection.
   */
  private function getTokenKey(): string {
    return $this->environmentResolver->getActiveEnvironmentName();
  }

  /**
   * Gets the name of the lock guarding token refresh.
   */
  private function getLockName(): string {
    return sprintf('ahjo-auth-%s', $this->environmentResolver->getActiveEnvironmentName());
  }

  /**
   * Make openid request.
   *
   * @param array $formParameters
   *   Request parameters.
   *
   * @throws \Drupal\paatokset_ahjo_api\AhjoOpenId\AhjoOpenIdException
   */
  private function makeTokenRequest(array $formParameters): AhjoAuthToken {
    $this->settings->assertValid();

    $lockName = $this->getLockName();

    // Refresh tokens are invalidated the moment it is used.
    // It is critical that only one refresh attempt is made.
    if (!$this->lock->acquire($lockName)) {
      throw new AhjoOpenIdException('Failed to acquire lock');
    }

    try {
      $client_id = $this->settings->clientId;
      $client_secret = $this->settings->clientSecret;

      $request = $this->httpClient->request('POST', $this->settings->tokenUrl, [
        'headers' => [
          'Authorization' => 'Basic ' . base64_encode($client_id . ':' . $client_secret),
        ],
        'form_params' => $formParameters,
      ]);

      $body = $request->getBody()->getContents();
      $data = json_decode($body);

      try {
        $token = AhjoAuthToken::fromAhjoResponse($data);
      }
      catch (\InvalidArgumentException $e) {
        throw new AhjoOpenIdException('Invalid token response: ' . $body, previous: $e);
      }

      // The token is persisted only on success. If the refresh fails, the
      // previous token and its refresh token stay in place so the next
      // refresh attempt can retry with the same refresh token.
      $this->keyValueFactory
        ->get(self::TOKEN_COLLECTION)
        ->set($this->getTokenKey(), json_encode($token, JSON_THROW_ON_ERROR));

      return $token;
    }
    catch (GuzzleException | \JsonException $e) {
      throw new AhjoOpenIdException($e->getMessage(), previous: $e);
    }
    finally {
      $this->lock->release($lockName);
    }
  }

  /**
   * Check if token is still valid.
   *
   * @return bool
   *   TRUE if token has not expired.
   */
  public function checkAuthToken(): bool {
    try {
      return !$this->getToken()->isExpired();
    }
    catch (\InvalidArgumentException) {
      // Missing or invalid token.
      return FALSE;
    }
  }

  /**
   * Reads the token from storage.
   *
   * @throws \InvalidArgumentException
   */
  private function readToken(): AhjoAuthToken {
    return AhjoAuthToken::fromJson((string) $this->keyValueFactory->get(self::TOKEN_COLLECTION)->get($this->getTokenKey(), ''));
  }

  /**
   * Gets token DTO.
   *
   * @throws \InvalidArgumentException
   */
  private function getToken(): AhjoAuthToken {
    try {
      $token = $this->readToken();

      if (!$token->isExpired()) {
        return $token;
      }
    }
    catch (\InvalidArgumentException) {
      // Missing or malformed token. A refresh may be in progress.
    }

    // Wait for a possible in-flight refresh, then re-read.
    $this->lock->wait($this->getLockName());

    return $this->readToken();
  }

  /**
   * Gets the access token.
   *
   * Warning: this does not check if the token is still valid.
   * It is up to the caller to check this.
   *
   * @return string
   *   The access token.
   */
  public function getAuthToken(): string {
    try {
      $token = $this->getToken();

      if ($token->isExpired()) {
        $this->logger->error('AHJO auth token expired');
      }

      return $token->token;
    }
    catch (\InvalidArgumentException $e) {
      Error::logException($this->logger, $e);

      return "";
    }
  }

  /**
   * Get token expiry data and time.
   */
  public function getAuthTokenExpiration(): int {
    try {
      return $this->getToken()->expires;
    }
    catch (\InvalidArgumentException) {
      return 0;
    }
  }

}
