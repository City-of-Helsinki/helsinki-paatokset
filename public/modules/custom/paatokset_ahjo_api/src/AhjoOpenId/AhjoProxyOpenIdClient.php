<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\AhjoOpenId;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\helfi_tunnistamo\ReauthenticatorInterface;
use Drupal\helfi_tunnistamo\TokenManagerInterface;
use Drupal\paatokset_ahjo_api\AhjoOpenId\DTO\AhjoProxyTokenStatus;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Client for the OpenID API of the standalone Ahjo proxy.
 */
final readonly class AhjoProxyOpenIdClient {

  public function __construct(
    private ClientInterface $httpClient,
    private ConfigFactoryInterface $configFactory,
    // Optional, so this module does not depend on helfi_tunnistamo.
    private ?TokenManagerInterface $tokenManager = NULL,
    private ?ReauthenticatorInterface $reauthenticator = NULL,
  ) {
  }

  /**
   * Checks if the current user has a tunnistamo session.
   */
  public function hasSession(): bool {
    return (bool) $this->tokenManager?->hasSession();
  }

  /**
   * Checks if the current user has a usable Helsinki AD access token.
   */
  public function hasValidSession(): bool {
    return (bool) $this->tokenManager?->getAccessToken();
  }

  /**
   * Logs the user in to Helsinki AD again.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Redirect to the identity provider.
   *
   * @throws \Drupal\paatokset_ahjo_api\AhjoOpenId\AhjoOpenIdException
   */
  public function reauthenticate(): Response {
    if (!$this->reauthenticator) {
      throw new AhjoOpenIdException('helfi_tunnistamo is not available.');
    }

    try {
      return $this->reauthenticator->reauthenticate();
    }
    catch (\LogicException $e) {
      throw new AhjoOpenIdException('Could not start Helsinki AD login: ' . $e->getMessage(), previous: $e);
    }
  }

  /**
   * Gets the status of the Ahjo auth token.
   *
   * @throws \Drupal\paatokset_ahjo_api\AhjoOpenId\AhjoOpenIdException
   */
  public function getTokenStatus(): AhjoProxyTokenStatus {
    $response = $this->request('GET', '/openid', FALSE);

    try {
      return AhjoProxyTokenStatus::fromJson((string) $response->getBody());
    }
    catch (\InvalidArgumentException $e) {
      throw new AhjoOpenIdException('Ahjo proxy returned invalid response: ' . $e->getMessage(), previous: $e);
    }
  }

  /**
   * Starts the OpenID flow.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect to the Ahjo auth URL.
   *
   * @throws \Drupal\paatokset_ahjo_api\AhjoOpenId\AhjoOpenIdException
   */
  public function startAuthFlow(): RedirectResponse {
    $response = $this->request('POST', '/openid', TRUE);

    $status = $response->getStatusCode();
    $location = $response->getHeaderLine('Location');
    if ($status < 300 || $status >= 400 || !str_starts_with($location, 'https://')) {
      throw new AhjoOpenIdException('Ahjo proxy did not return an auth URL.', $status);
    }

    $response = new TrustedRedirectResponse($location);

    // The auth URL contains a single-use state.
    $response->getCacheableMetadata()->setCacheMaxAge(0);

    return $response;
  }

  /**
   * Makes an authenticated request to the proxy.
   *
   * @throws \Drupal\paatokset_ahjo_api\AhjoOpenId\AhjoOpenIdException
   *   The exception code is the HTTP status of the proxy response, if any.
   * @throws \Drupal\paatokset_ahjo_api\AhjoOpenId\AhjoProxySessionExpiredException
   *   If the user has no valid Helsinki AD access token.
   */
  private function request(string $method, string $path, bool $requireAdmin): ResponseInterface {
    $settings = $this->configFactory->get('paatokset_ahjo_api.settings');
    $url = (string) $settings->get('proxy_v3_url');
    $apiKey = (string) $settings->get('proxy_v3_api_key');

    if (!$url || !$apiKey) {
      throw new AhjoOpenIdException('Ahjo proxy is not configured.');
    }

    $headers = [
      'api-key' => $apiKey,
      'Accept' => 'application/json',
    ];

    if ($requireAdmin) {
      // Renews the access token if needed.
      $accessToken = $this->tokenManager?->getAccessToken();
      if (!$accessToken) {
        throw new AhjoProxySessionExpiredException('Helsinki AD session is missing or expired.');
      }

      $headers['X-Access-Token'] = $accessToken;
    }

    try {
      return $this->httpClient->request($method, rtrim($url, '/') . $path, [
        RequestOptions::HEADERS => $headers,
        RequestOptions::TIMEOUT => 10,
        RequestOptions::ALLOW_REDIRECTS => FALSE,
      ]);
    }
    catch (GuzzleException $e) {
      $status = $e instanceof RequestException ? (int) $e->getResponse()?->getStatusCode() : 0;
      throw new AhjoOpenIdException('Ahjo proxy request failed: ' . $e->getMessage(), $status, $e);
    }
  }

}
