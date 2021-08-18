<?php

declare(strict_types = 1);

namespace Drupal\paatokset_ahjo_openid;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Handler for AHJO API Open ID connector.
 *
 * @package Drupal\paatokset_ahjo_openid
 */
class AhjoOpenId implements ContainerInjectionInterface {

  /**
   * State API.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  private $state;

  /**
   * The config for the integration.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * HTTP Client.
   *
   * @var GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Messenger interface.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs AhjoOpenId Controller.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   HTTP Client.
   * @param \Drupal\Core\State\StateInterface $state
   *   State API.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Messenger.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(ConfigFactoryInterface $config_factory, ClientInterface $http_client, StateInterface $state, MessengerInterface $messenger) {
    $this->httpClient = $http_client;
    $this->state = $state;
    $this->messenger = $messenger;
    $this->config = $config_factory->get('paatokset_ahjo_openid.settings');

    $this->authUrl = $this->config->get('auth_url');
    $this->tokenUrl = $this->config->get('token_url');
    $this->callbackUrl = $this->config->get('callback_url');
    $this->clientId = $this->config->get('client_id');
    $this->openIdScope = $this->config->get('scope');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('http_client'),
      $container->get('state'),
      $container->get('messenger')
    );
  }

  /**
   * Get authentication URL.
   *
   * @return string|null
   *   Auth URL.
   */
  public function getAuthUrl(): ?string {
    if (empty($this->authUrl) || empty($this->callbackUrl) || empty($this->clientId) ||empty($this->openIdScope)) {
      return NULL;
    }
    return $this->authUrl . '?client_id=' . $this->clientId . '&scope=' . $this->openIdScope . '&response_type=code&redirect_uri=' . $this->callbackUrl;
  }

  /**
   * Get Auth and refresh tokens.
   */
  public function getAuthAndRefreshTokens(string $code = NULL) {
    $request = $this->httpClient->request(
      'POST',
      $this->tokenUrl,
      [
        'http_errors' => FALSE,
        'headers' => $this->getHeaders(),
        'form_params' => [
          'client_id' => $this->clientId,
          'grant_type' => 'authorization_code',
          'code' => $code,
          'redirect_uri' => $this->callbackUrl,
        ],
      ]
    );

    $data = json_decode((string) $request->getBody());

    if (isset($data->access_token) && isset($data->refresh_token)) {
      $this->setAuthToken($data->access_token, $data->expires_in);
      $this->state->set('ahjo_api_refresh_token', $data->refresh_token);
    }

    return $data;
  }

  /**
   * Refresh AUTH token.
   *
   * @return string
   *   Auth token.
   */
  public function refreshAuthToken(): ?string {
    $refresh_token = $this->state->get('ahjo_api_refresh_token');
    $request = $this->httpClient->request(
      'POST',
      $this->tokenUrl,
      [
        'http_errors' => FALSE,
        'headers' => $this->getHeaders(),
        'form_params' => [
          'client_id' => $this->clientId,
          'grant_type' => 'refresh_token',
          'refresh_token' => $refresh_token,
        ],
      ]
    );

    $data = json_decode((string) $request->getBody());

    if (!empty($data->access_token) && !empty($data->refresh_token)) {
      $this->setAuthToken($data->access_token, $data->expires_in);
      $this->state->set('ahjo_api_refresh_token', $data->refresh_token);
      return $data->access_token;
    }
    return NULL;
  }

  /**
   * Check if token is still valid.
   *
   * @return bool
   *   Auth token.
   */
  public function checkAuthToken(): bool {
    $auth_expiration = (int) $this->state->get('ahjo-api-auth-expiration');
    if (time() > $auth_expiration) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Gets the auth token state variable.
   *
   * @return string
   *   Auth token.
   */
  public function getAuthToken(): ?string {
    return $this->state->get('ahjo-api-auth-key');
  }

  /**
   * Get token expiry data and time.
   */
  public function getAuthTokenExpiration(): int {
    return (int) $this->state->get('ahjo-api-auth-expiration');
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
    $this->state->set('ahjo-api-auth-key', $token);
    $this->state->set('ahjo-api-auth-expiration', time() + $expiration);
  }

  /**
   * Get headers for HTTP requests.
   *
   * @return array|null
   *   Headers for the request or NULL if config is missing.
   */
  private function getHeaders(): ?array {
    $client_id = $this->clientId;
    $client_secret = getenv('PAATOKSET_OPENID_SECRET');

    if (empty($client_id) || empty($client_secret)) {
      return NULL;
    }
    return ['Authorization' => 'Basic ' . base64_encode($client_id . ':' . $client_secret)];
  }

}
