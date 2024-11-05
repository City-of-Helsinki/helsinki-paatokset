<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_openid;

use Drupal\Core\State\StateInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Utils;

/**
 * Handler for AHJO API Open ID connector.
 *
 * @package Drupal\paatokset_ahjo_openid
 */
class AhjoOpenId {

  private const STATE_AUTH_TOKEN = 'ahjo-api-auth-key';
  private const STATE_AUTH_TOKEN_EXPIRATION = 'ahjo-api-auth-expiration';
  private const STATE_REFRESH_TOKEN = 'ahjo_api_refresh_token';

  /**
   * Constructs AhjoOpenId Controller.
   *
   * @param \Drupal\paatokset_ahjo_openid\Settings $settings
   *   Open id configuration.
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   HTTP Client.
   * @param \Drupal\Core\State\StateInterface $state
   *   State API.
   */
  public function __construct(
    private readonly Settings $settings,
    private readonly ClientInterface $httpClient,
    private readonly StateInterface $state,
  ) {
  }

  /**
   * Check if connector is configured.
   *
   * @return bool
   *   FALSE if connector has missing configs.
   */
  public function isConfigured(): bool {
    // Missing config options.
    if (!$this->validateSettings()) {
      return FALSE;
    }

    // Missing refresh token.
    if (!$this->state->get(self::STATE_REFRESH_TOKEN)) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Get authentication URL.
   *
   * @return string|null
   *   Auth URL.
   */
  public function getAuthUrl(): ?string {
    if (!$this->validateSettings()) {
      return NULL;
    }

    return $this->settings->authUrl . '?client_id=' . $this->settings->clientId . '&scope=' . $this->settings->openIdScope . '&response_type=code&redirect_uri=' . $this->settings->callbackUrl;
  }

  /**
   * Check if openid settings are configured.
   *
   * @return bool
   *   FALSE if connector has missing configs.
   */
  private function validateSettings(): bool {
    $vars = get_object_vars($this->settings);

    foreach ($vars as $key => $value) {
      if (empty($this->settings->{$key})) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Get Auth and refresh tokens.
   *
   * @return mixed
   *   Decoded json response
   *
   * @throws \Drupal\paatokset_ahjo_openid\AhjoOpenIdException
   */
  public function getAuthAndRefreshTokens(string $code = NULL): mixed {
    // getHeaders throw an exception if settings are not valid.
    if (!$this->validateSettings()) {
      throw new \InvalidArgumentException("Ahjo Open Id is not configured");
    }

    $data = $this->makeTokenRequest([
      'client_id' => $this->settings->clientId,
      'grant_type' => 'authorization_code',
      'code' => $code,
      'redirect_uri' => $this->settings->callbackUrl,
    ]);

    if (isset($data->access_token, $data->refresh_token, $data->expires_in)) {
      $this->setAuthToken($data->access_token, $data->expires_in);
      $this->state->set(self::STATE_REFRESH_TOKEN, $data->refresh_token);

      return $data;
    }

    throw new AhjoOpenIdException("Invalid token response");
  }

  /**
   * Refresh AUTH token.
   *
   * @throws \Drupal\paatokset_ahjo_openid\AhjoOpenIdException
   */
  private function refreshAuthToken(): void {
    // getHeaders throw an exception if settings are not valid.
    // Refresh token is required.
    if (!$this->isConfigured()) {
      throw new \InvalidArgumentException("Ahjo Open Id is not configured");
    }

    $refresh_token = $this->state->get(self::STATE_REFRESH_TOKEN);
    $data = $this->makeTokenRequest([
      'client_id' => $this->settings->clientId,
      'grant_type' => 'refresh_token',
      'refresh_token' => $refresh_token,
    ]);

    if (empty($data->access_token) || empty($data->refresh_token) || empty($data->expires_in)) {
      throw new AhjoOpenIdException("Invalid token response");
    }
    else {
      $this->setAuthToken($data->access_token, $data->expires_in);
      $this->state->set(self::STATE_REFRESH_TOKEN, $data->refresh_token);
    }
  }

  /**
   * Make openid request.
   *
   * @param array $formParameters
   *   Request parameters.
   *
   * @return mixed
   *   Decoded json response.
   *
   * @throws \Drupal\paatokset_ahjo_openid\AhjoOpenIdException
   */
  private function makeTokenRequest(array $formParameters): mixed {
    try {
      $request = $this->httpClient->request('POST', $this->settings->tokenUrl, [
        'headers' => $this->getHeaders(),
        'form_params' => $formParameters,
      ]);

      return Utils::jsonDecode($request->getBody()->getContents());
    }
    catch (GuzzleException $e) {
      throw new AhjoOpenIdException($e->getMessage(), previous: $e);
    }
  }

  /**
   * Check if token is still valid.
   *
   * @return bool
   *   TRUE if token has not expired.
   */
  public function checkAuthToken(): bool {
    if (!$this->state->get(self::STATE_AUTH_TOKEN)) {
      return FALSE;
    }

    $auth_expiration = $this->getAuthTokenExpiration();
    if (time() > $auth_expiration) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Gets the access token.
   *
   * @param bool $refresh
   *   Force token refresh.
   *
   * @return string
   *   The access token.
   *
   * @throws \Drupal\paatokset_ahjo_openid\AhjoOpenIdException
   */
  public function getAuthToken(bool $refresh = FALSE): string {
    if ($refresh) {
      // Refresh the access token.
      $this->refreshAuthToken();
    }

    return (string) $this->state->get(self::STATE_AUTH_TOKEN);
  }

  /**
   * Get token expiry data and time.
   */
  public function getAuthTokenExpiration(): int {
    return (int) $this->state->get(self::STATE_AUTH_TOKEN_EXPIRATION);
  }

  /**
   * Sets the auth token state variable.
   *
   * @param string $token
   *   Auth token.
   * @param int $expiration
   *   Token lifetime.
   */
  private function setAuthToken(string $token, int $expiration): void {
    $this->state->set(self::STATE_AUTH_TOKEN, $token);
    $this->state->set(self::STATE_AUTH_TOKEN_EXPIRATION, time() + $expiration);
  }

  /**
   * Get cookies for API requests. Workaround for local environment faults.
   *
   * @return string|null
   *   Cookie. NULL if value does not exists.
   */
  public function getCookies(): ?string {
    return $this->state->get('ahjo_api_cookies');
  }

  /**
   * Get headers for HTTP requests.
   *
   * @return array
   *   Headers for the request.
   */
  private function getHeaders(): array {
    $client_id = $this->settings->clientId;
    $client_secret = $this->settings->clientSecret;

    if (empty($client_id) || empty($client_secret)) {
      throw new \InvalidArgumentException('OpenID client is not configured');
    }

    return ['Authorization' => 'Basic ' . base64_encode($client_id . ':' . $client_secret)];
  }

}
