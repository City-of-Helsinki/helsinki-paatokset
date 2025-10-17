<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\AhjoOpenId;

use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Utility\Error;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\paatokset_ahjo_api\AhjoOpenId\DTO\AhjoAuthToken;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Utils;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Handler for AHJO API Open ID connector.
 */
class AhjoOpenId implements LoggerAwareInterface {

  use LoggerAwareTrait;

  public const string AHJO_AUTH_OLD = 'ahjo-auth-old';

  public function __construct(
    private readonly Settings $settings,
    private readonly ClientInterface $httpClient,
    private readonly StateInterface $state,
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
   * Get ahjo token state key.
   */
  private function getTokenKey(): string {
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

    $tokenKey = $this->getTokenKey();

    // Refresh tokens are invalidated the moment it is used.
    // It is critical that only one refresh attempt is made.
    if (!$this->lock->acquire($tokenKey)) {
      throw new AhjoOpenIdException('Failed to acquire lock');
    }

    try {
      // Clear the current token. The old token is stored in
      // the state to help with debugging issues. The previous
      // token is invalid after the refreshing, so it is not
      // strictly necessary anymore. However, settings the current
      // token to empty value makes it easier to detect that an
      // invalid token is used, in case the refresh fails.
      if ($old = $this->state->get($tokenKey)) {
        $this->state->set(self::AHJO_AUTH_OLD, $old);
        $this->state->set($tokenKey, '');
      }

      $client_id = $this->settings->clientId;
      $client_secret = $this->settings->clientSecret;

      $request = $this->httpClient->request('POST', $this->settings->tokenUrl, [
        'headers' => [
          'Authorization' => 'Basic ' . base64_encode($client_id . ':' . $client_secret),
        ],
        'form_params' => $formParameters,
      ]);

      $body = $request->getBody()->getContents();
      $data = Utils::jsonDecode($body);

      try {
        $token = AhjoAuthToken::fromAhjoResponse($data);
      }
      catch (\InvalidArgumentException $e) {
        throw new AhjoOpenIdException('Invalid token response: ' . $body, previous: $e);
      }

      $this->state->set($tokenKey, json_encode($token));

      return $token;
    }
    catch (GuzzleException $e) {
      throw new AhjoOpenIdException($e->getMessage(), previous: $e);
    }
    finally {
      $this->lock->release($tokenKey);
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
   * Gets token DTO.
   *
   * @throws \InvalidArgumentException
   */
  private function getToken(): AhjoAuthToken {
    return AhjoAuthToken::fromJson($this->state->get($this->getTokenKey(), ''));
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
