<?php

declare(strict_types = 1);

namespace Drupal\paatokset_ahjo_proxy;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\paatokset_ahjo_openid\AhjoOpenId;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Handler for AHJO API Proxy.
 *
 * @package Drupal\paatokset_ahjo_proxy
 */
class AhjoProxy implements ContainerInjectionInterface {

  /**
   * Base URL for API.
   *
   * @var string
   */
  protected const API_BASE_URL = 'https://ahjohyte.hel.fi:9802/ahjorest/v1/';

  /**
   * Whether to return static fallback files.
   *
   * @var bool
   */
  protected bool $useStaticFallbacks = TRUE;

  /**
   * HTTP Client.
   *
   * @var GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Ahjo Open ID service.
   *
   * @var \Drupal\paatokset_ahjo_openid\AhjoOpenId
   */
  protected $ahjoOpenId;

  /**
   * The cache.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected CacheBackendInterface $dataCache;

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() : int {
    return time() + 60 * 60;
  }

  /**
   * Whether to use request cache or not.
   *
   * @var bool
   */
  protected bool $useRequestCache = TRUE;

  /**
   * Constructs Ahjo Proxy service.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   HTTP Client.
   * @param \Drupal\paatokset_ahjo_openid\AhjoOpenId
   *   Ahjo Open ID service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(ClientInterface $http_client, CacheBackendInterface $data_cache, AhjoOpenId $ahjo_open_id) {
    $this->httpClient = $http_client;
    $this->dataCache = $data_cache;
    $this->ahjoOpenId = $ahjo_open_id;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client'),
      $container->get('cache.default'),
      $container->get('paatokset_ahjo_openid')
    );
  }

  /**
   * Get metting data
   *
   * @param string|null $query_string
   *   Query string to pass on to endpoint.
   *
   * @return array
   *   Data from endpoint or static file.
   */
  public function getMeetings(?string $query_string): array {
    if ($this->useStaticFallbacks) {
      return $this->getStatic('meetings.json');
    }

    $meetings_url = self::API_BASE_URL . 'meetings/?' . urldecode($query_string);
    $meetings = $this->getContent($meetings_url);

    if (empty($meetings['meetings'])) {
      return $meetings;
    }

    // Follow single meeting URLs to get full data.
    // TODO: Limit amount of requests.
    $meetings_full = [];
    foreach ($meetings['meetings'] as $meeting) {
      if (empty($meeting['links'])) {
        continue;
      }

      $self_url = $this->getSelfUrl($meeting['links']);
      $meeting_content = $this->getContent($self_url);
      if (!empty($meeting_content)) {
        $meetings_full[] = $meeting_content;
      }
    }

    // If we got any full meeting results, return them and update count.
    if (!empty($meetings_full)) {
      $meetings['meetings'] = $meetings_full;
      $meetings['count'] = count($meetings_full);
    }

    return $meetings;
  }

  /**
   * Return content from static JSON files.
   *
   * @param string $filename
   *   File to load.
   *
   * @return array
   *   Data from file or empty array.
   */
  public function getStatic(string $filename): array {
    $file_path = \Drupal::service('extension.list.module')->getPath('paatokset_ahjo_proxy') . '/static/' . $filename;
    $file_contents = file_get_contents($file_path);
    if ($file_contents) {
      $data = \GuzzleHttp\json_decode($file_contents, TRUE);
      return $data['data'] ?? [];
    }
    return [];
  }

  /**
   * Get rel=self link.
   *
   * @param array $links
   *   Links array.
   *
   * @return string|null
   *   Self link or NULL if not found.
   */
  protected function getSelfUrl(array $links): ?string {
    foreach ($links as $link) {
      if (isset($link['rel']) && isset($link['href']) && $link['rel'] === 'self') {
        return $link['href'];
      }
    }
    return NULL;
  }

  /**
   * Sends a HTTP request and returns response data as array.
   *
   * @param string $url
   *   The url.
   *
   * @return array
   *   The JSON returned by API service.
   */
  protected function getContent(string $url) : array {
    if ($data = $this->getFromCache($url)) {
      return $data;
    }

    try {
      $response = $this->httpClient->request('GET', $url,
      [
        'http_errors' => FALSE,
        'headers' => $this->getAuthHeaders(),
      ]);

      if ($response->getStatusCode() !== 200) {
        return [];
      }

      $content = (string) $response->getBody();
      $content = \GuzzleHttp\json_decode($content, TRUE);
      $this->setCache($url, $content);

      return $content ?? [];
    }
    catch (GuzzleException $e) {
    }
    return [];
  }

  /**
   * Get authentication headers for HTTP requests.
   *
   * @return array
   *   Headers for the request or empty array if config/token is missing.
   */
  private function getAuthHeaders(): ?array {
    // Check if access token is still valid (not expired).
    if ($this->ahjoOpenId->getAuthToken()) {
      $access_token = $this->ahjoOpenId->getAuthToken();
    }
    else {
      // Refresh and return new access token.
      $access_token = $this->ahjoOpenId->refreshAuthToken();
    }

    if (!$access_token) {
      return [];
    }

    return [
      'Authorization' => 'Bearer ' . $access_token,
    ];
  }

  /**
   * Gets the cache key for given id.
   *
   * @param string $id
   *   The id.
   *
   * @return string
   *   The cache key.
   */
  protected function getCacheKey(string $id) : string {
    $id = preg_replace('/[^a-z0-9_]+/s', '_', $id);

    return sprintf('ahjo-proxy-%s', $id);
  }

  /**
   * Gets cached data for given id.
   *
   * @param string $id
   *   The id.
   *
   * @return array|null
   *   The cached data or null.
   */
  protected function getFromCache(string $id) : ? array {
    if (!$this->useRequestCache) {
      return NULL;
    }
    $key = $this->getCacheKey($id);

    if (isset($this->data[$key])) {
      return $this->data[$key];
    }

    if ($data = $this->dataCache->get($key)) {
      return $data->data;
    }
    return NULL;
  }

  /**
   * Sets the cache.
   *
   * @param string $id
   *   The id.
   * @param mixed $data
   *   The data.
   */
  protected function setCache(string $id, $data) : void {
    if (!$this->useRequestCache) {
      return;
    }
    $key = $this->getCacheKey($id);
    $this->dataCache->set($key, $data, $this->getCacheMaxAge(), []);
  }

}
