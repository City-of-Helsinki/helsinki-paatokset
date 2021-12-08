<?php

declare(strict_types = 1);

namespace Drupal\paatokset_ahjo_proxy;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\file\FileInterface;
use Drupal\paatokset_ahjo_openid\AhjoOpenId;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\Psr7\Response;

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
  protected const API_BASE_URL = 'https://ahjo.hel.fi:9802/ahjorest/v1/';

  /**
   * Base URL for files.
   *
   * @var string
   */
  protected const API_FILE_URL = 'https://ahjo.hel.fi:9802/ahjorest/v1/content/';

  /**
   * HTTP Client.
   *
   * @var GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

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
   * @param \Drupal\Core\Cache\CacheBackendInterface $data_cache
   *   Data Cache.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory service.
   * @param \Drupal\paatokset_ahjo_openid\AhjoOpenId $ahjo_open_id
   *   Ahjo Open ID service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(ClientInterface $http_client, CacheBackendInterface $data_cache, EntityTypeManagerInterface $entity_type_manager, LoggerChannelFactoryInterface $logger_factory, AhjoOpenId $ahjo_open_id) {
    $this->httpClient = $http_client;
    $this->dataCache = $data_cache;
    $this->ahjoOpenId = $ahjo_open_id;
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger_factory->get('paatokset_ahjo_proxy');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client'),
      $container->get('cache.default'),
      $container->get('entity_type.manager'),
      $container->get('logger.factory'),
      $container->get('paatokset_ahjo_openid')
    );
  }

  /**
   * Proxy data from API.
   *
   * @param string $url
   *   Endpoint to get data from.
   * @param string|null $query_string
   *   Query string to pass on to API.
   *
   * @return array
   *   Data from endpoint as array.
   */
  public function getData(string $url, ?string $query_string): array {
    if ($query_string === NULL) {
      $query_string = '';
    }

    if ($url === 'decisionmakers') {
      $url = 'agents/decisionmakers';
    }

    $api_url = self::API_BASE_URL . $url . '/?' . urldecode($query_string);
    $data = $this->getContent($api_url);
    return $data;
  }

  /**
   * Get full content for a single item from list API.
   *
   * @param array $item
   *   Item to get data for.
   *
   * @return array|null
   *   Full data or NULL if self link isn't found.
   */
  public function getFullContentForItem(array $item): ?array {
    if (!isset($item['links'])) {
      return NULL;
    }

    $item_url = $this->getSelfUrl($item['links']);

    if (!$item_url) {
      return NULL;
    }

    $data = $this->getContent($item_url);
    return $data;
  }

  /**
   * Get meeting data.
   *
   * @param string|null $query_string
   *   Query string to pass on to endpoint.
   *
   * @return array
   *   Data from endpoint or static file.
   */
  public function getMeetings(?string $query_string): array {
    if ($query_string === NULL) {
      $query_string = '';
    }

    $meetings_url = self::API_BASE_URL . 'meetings/?' . urldecode($query_string);
    $meetings = $this->getContent($meetings_url);

    if (empty($meetings['meetings'])) {
      return $meetings;
    }

    // Follow single meeting URLs to get full data.
    // @todo Limit amount of requests.
    $meetings_full = [];
    foreach ($meetings['meetings'] as $meeting) {
      if (empty($meeting['links'])) {
        continue;
      }

      $self_url = $this->getSelfUrl($meeting['links']);
      $meeting_content = $this->getContent($self_url);
      if (!empty($meeting_content) && !empty($meeting_content['MeetingID'])) {
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
   * Get single meeting from Ahjo API.
   *
   * @param string $id
   *   Meeting ID.
   * @param string|null $query_string
   *   Query string to pass on.
   * @param bool $bypass_cache
   *   Bypass request cache.
   *
   * @return array
   *   Meeting data inside 'meetings' to normalize output for migrations.
   */
  public function getSingleMeeting(string $id, ?string $query_string, bool $bypass_cache = FALSE): array {
    if ($query_string === NULL) {
      $query_string = '';
    }
    $meeting_url = self::API_BASE_URL . 'meetings/' . $id . '?' . urldecode($query_string);
    $meeting = $this->getContent($meeting_url, $bypass_cache);
    return ['meetings' => [$meeting]];
  }

  /**
   * Get single case from Ahjo API.
   *
   * @param string $id
   *   Case ID.
   * @param string|null $query_string
   *   Query string to pass on.
   * @param bool $bypass_cache
   *   Bypass request cache.
   *
   * @return array
   *   Cases data inside 'cases' to normalize output for migrations.
   */
  public function getSingleCase(string $id, ?string $query_string, bool $bypass_cache = FALSE): array {
    if ($query_string === NULL) {
      $query_string = '';
    }
    $meeting_url = self::API_BASE_URL . 'cases/' . $id . '?' . urldecode($query_string);
    $meeting = $this->getContent($meeting_url, $bypass_cache);
    return ['cases' => [$meeting]];
  }

  /**
   * Get aggregated data.
   *
   * @param string $dataset
   *   Which dataset to fetch.
   *
   * @return array
   *   Aggregated data from static file.
   */
  public function getAggregatedData(string $dataset): array {
    switch ($dataset) {
      case 'meetings_all':
        $filename = 'meetings_all.json';
        break;

      case 'meetings_latest':
        $filename = 'meetings_latest.json';
        break;

      case 'decisions_all':
        $filename = 'decisions_all.json';
        break;

      case 'decisions_latest':
        $filename = 'decisions_latest.json';
        break;

      case 'cases_all':
        $filename = 'cases_all.json';
        break;

      case 'cases_latest':
        $filename = 'cases_latest.json';
        break;

      case 'decisionmakers':
        $filename = 'decisionmakers.json';
        break;

      default:
        return [];
    }

    return $this->getStatic($filename);
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
    /** @var \Drupal\file\FileInterface[] $files */
    $files = $this->entityTypeManager
      ->getStorage('file')
      ->loadByProperties(['uri' => 'public://' . $filename]);
    /** @var \Drupal\file\FileInterface|null $file */
    $file = reset($files);

    if (!$file instanceof FileInterface) {
      return [];
    }
    $file_contents = file_get_contents($file->getFileUri());

    if ($file_contents) {
      $data = \GuzzleHttp\json_decode($file_contents, TRUE);
      return $data ?? [];
    }
    return [];
  }

  /**
   * Static callback for aggregating items in batch.
   *
   * @param mixed $data
   *   Data for operation.
   * @param mixed $context
   *   Context for batch operation.
   */
  public static function processBatchItem($data, &$context) {
    $context['message'] = 'Importing item number ' . $data['count'];

    if (!isset($context['results']['starttime'])) {
      $context['results']['starttime'] = microtime(TRUE);
    }
    if (!isset($context['results']['items'])) {
      $context['results']['items'] = [];
    }
    if (!empty($data['append'])) {
      $context['results']['items'] = $data['append'];
    }
    if (!isset($context['results']['failed'])) {
      $context['results']['failed'] = [];
    }
    if (!isset($context['results']['filename'])) {
      $context['results']['filename'] = $data['filename'];
    }
    if (!isset($context['results']['list_key'])) {
      $context['results']['list_key'] = $data['list_key'];
    }
    if (!isset($context['results']['endpoint'])) {
      $context['results']['endpoint'] = $data['endpoint'];
    }
    if (!isset($context['results']['dataset'])) {
      $context['results']['dataset'] = $data['dataset'];
    }

    /** @var \Drupal\paatokset_ahjo_proxy\AhjoProxy $ahjo_proxy */
    $ahjo_proxy = \Drupal::service('paatokset_ahjo_proxy');
    $full_data = $ahjo_proxy->getFullContentForItem($data['item']);

    if (!empty($full_data)) {
      $context['results']['items'][] = $full_data;
    }
    else {
      $context['results']['failed'][] = $data['item'];
    }
  }

  /**
   * Static callback function for finishing aggregation batch.
   *
   * @param mixed $success
   *   If batch succeeded or not.
   * @param array $results
   *   Aggregated results.
   * @param array $operations
   *   Operations with errors.
   */
  public static function finishBatch($success, array $results, array $operations) {
    $messenger = \Drupal::messenger();
    $total = count($results['items']);

    $end_time = microtime(TRUE);
    $total_time = ($end_time - $results['starttime']);
    $messenger->addMessage('Processed ' . $total . ' items in ' . $total_time . ' seconds.');
    $messenger->addMessage('Items failed: ' . count($results['failed']));

    if (!empty($results['filename'])) {
      $filename = $results['filename'];
    }
    else {
      $filename = $results['endpoint'] . '_' . $results['dataset'] . '.json';
    }

    file_save_data(json_encode([$results['list_key'] => $results['items']]), 'public://' . $filename, FileSystemInterface::EXISTS_REPLACE);
    $messenger->addMessage('Aggregated data saved into public://' . $filename);

    // Save failed array into filesystem even if it's empty so we can wipe it.
    file_save_data(json_encode($results['failed']), 'public://failed_' . $filename, FileSystemInterface::EXISTS_REPLACE);
    if (!empty($results['failed'])) {
      $messenger->addMessage('Data for failed items saved into public://failed_' . $filename);
    }
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
   * @param bool $bypass_cache
   *   Bypass request cache.
   *
   * @return array
   *   The JSON returned by API service.
   */
  protected function getContent(string $url, bool $bypass_cache = FALSE) : array {
    if (!$bypass_cache && $data = $this->getFromCache($url)) {
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
    catch (\Exception $e) {
    }
    return [];
  }

  /**
   * Gets a file from Ahjo API and returns the Response.
   *
   * @param string $nativeID
   *   Native ID for the file. Should already be urlencoded.
   *
   * @return GuzzleHttp\Psr7\Response
   *   Response from the API.
   */
  public function getFile(string $nativeId): ?Response {
    $url = self::API_FILE_URL . $nativeId;

    try {
      $response = $this->httpClient->request('GET', $url,
      [
        'http_errors' => FALSE,
        'headers' => $this->getAuthHeaders(),
      ]);

      if ($response->getStatusCode() !== 200) {
        return [];
      }

      return $response;
    }
    catch (\Exception $e) {
    }
    return NULL;
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
