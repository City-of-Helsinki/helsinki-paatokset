<?php

declare(strict_types = 1);

namespace Drupal\paatokset_ahjo_proxy;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Url;
use Drupal\file\FileInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrationPluginManager;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\paatokset_ahjo_openid\AhjoOpenId;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Handler for AHJO API Proxy.
 *
 * @package Drupal\paatokset_ahjo_proxy
 */
class AhjoProxy implements ContainerInjectionInterface {

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
   * Migration manager.
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManager
   */
  protected $migrationManager;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * File repository service.
   *
   * @var \Drupal\file\FileRepositoryInterface
   */
  protected $fileRepository;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

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
   * The database.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

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
   * @param \Drupal\migrate\Plugin\MigrationPluginManager $migration_manager
   *   Migration manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory service.
   * @param \Drupal\file\FileRepositoryInterface $file_repository
   *   File repository.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\paatokset_ahjo_openid\AhjoOpenId $ahjo_open_id
   *   Ahjo Open ID service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(ClientInterface $http_client, CacheBackendInterface $data_cache, EntityTypeManagerInterface $entity_type_manager, MigrationPluginManager $migration_manager, LoggerChannelFactoryInterface $logger_factory, FileRepositoryInterface $file_repository, ConfigFactoryInterface $config_factory, Connection $database, AhjoOpenId $ahjo_open_id) {
    $this->httpClient = $http_client;
    $this->dataCache = $data_cache;
    $this->ahjoOpenId = $ahjo_open_id;
    $this->entityTypeManager = $entity_type_manager;
    $this->migrationManager = $migration_manager;
    $this->fileRepository = $file_repository;
    $this->logger = $logger_factory->get('paatokset_ahjo_proxy');
    $this->config = $config_factory;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client'),
      $container->get('cache.default'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.migration'),
      $container->get('logger.factory'),
      $container->get('file.repository'),
      $container->get('config.factory'),
      $container->get('database'),
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

    // Special case for fetching decisionmakers.
    if ($url === 'decisionmakers') {
      $url = 'agents/decisionmakers';
    }

    $api_url = $this->getApiBaseUrl() . $url . '/?' . urldecode($query_string);

    // Local adjustments for fetching records through proxy.
    if (!empty(getenv('AHJO_PROXY_BASE_URL')) && strpos($url, 'records') === 0) {
      $base_url = getenv('AHJO_PROXY_BASE_URL');
      $api_url = $base_url . 'fi/ahjo-proxy/' . $url . '?' . urldecode($query_string);
    }

    // Local adjustments for fetching meetings through proxy.
    if (!empty(getenv('AHJO_PROXY_BASE_URL')) && strpos($url, 'meetings') === 0) {
      $base_url = getenv('AHJO_PROXY_BASE_URL');
      $api_url = $base_url . 'fi/ahjo-proxy/' . $url . '?' . urldecode($query_string);
    }

    // Local adjustments for fetching cases or decisions through proxy.
    if (!empty(getenv('AHJO_PROXY_BASE_URL'))) {
      if (strpos($url, 'cases') === 0 || strpos($url, 'decisions') === 0 || strpos($url, 'agenda-item') === 0) {
        $base_url = getenv('AHJO_PROXY_BASE_URL');
        $api_url = $base_url . 'fi/ahjo-proxy/' . $url . '?' . urldecode($query_string);
      }
    }

    // Local adjustments for fetching org data through proxy.
    if (!empty(getenv('AHJO_PROXY_BASE_URL'))) {
      if (strpos($url, 'organization') === 0) {
        $base_url = getenv('AHJO_PROXY_BASE_URL');
        $api_url = $base_url . 'fi/ahjo-proxy/' . $url . '?' . urldecode($query_string);
      }
    }

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

    if (!empty($data) && strpos($item_url, "decisions/")) {
      $data = array_shift($data);
    }

    return $data;
  }

  /**
   * Get meeting data.
   *
   * @param string|null $query_string
   *   Query string to pass on to endpoint.
   *
   * @return array
   *   Data from endpoint.
   */
  public function getMeetings(?string $query_string): array {
    if ($query_string === NULL) {
      $query_string = '';
    }

    $meetings_url = $this->getApiBaseUrl() . 'meetings/?' . urldecode($query_string);
    $meetings = $this->getContent($meetings_url);

    return $meetings;
  }

  /**
   * Get cases data.
   *
   * @param string|null $query_string
   *   Query string to pass on to endpoint.
   *
   * @return array
   *   Data from endpoint.
   */
  public function getCases(?string $query_string): array {
    if ($query_string === NULL) {
      $query_string = '';
    }

    $cases_url = $this->getApiBaseUrl() . 'cases/?' . urldecode($query_string);
    $cases = $this->getContent($cases_url);

    return $cases;
  }

  /**
   * Get decisions data.
   *
   * @param string|null $query_string
   *   Query string to pass on to endpoint.
   *
   * @return array
   *   Data from endpoint.
   */
  public function getDecisions(?string $query_string): array {
    if ($query_string === NULL) {
      $query_string = '';
    }

    $decisions_url = $this->getApiBaseUrl() . 'decisions/?' . urldecode($query_string);
    $decisions = $this->getContent($decisions_url);

    return $decisions;
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
    $meeting_url = $this->getApiBaseUrl() . 'meetings/' . strtoupper($id) . '?' . urldecode($query_string);
    $meeting = $this->getContent($meeting_url, $bypass_cache);
    return ['meetings' => [$meeting]];
  }

  /**
   * Get single agenda item from Ahjo API.
   *
   * @param string $meeting_id
   *   Meeting ID.
   * @param string $id
   *   Agenda item document ID.
   * @param string|null $query_string
   *   Query string to pass on.
   * @param bool $bypass_cache
   *   Bypass request cache.
   *
   * @return array
   *   Agenad item data.
   */
  public function getAgendaItem(string $meeting_id, string $id, ?string $query_string, bool $bypass_cache = FALSE): array {
    if ($query_string === NULL) {
      $query_string = '';
    }
    $agenda_item_url = $this->getApiBaseUrl() . 'meetings/' . strtoupper($meeting_id) . '/agendaitems' . '/' . $id . '?' . urldecode($query_string);
    $agenda_item = $this->getContent($agenda_item_url, $bypass_cache);
    return ['agenda_item' => $agenda_item];
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
    $cases_url = $this->getApiBaseUrl() . 'cases/' . strtoupper($id) . '?' . urldecode($query_string);
    $case = $this->getContent($cases_url, $bypass_cache);
    return ['cases' => [$case]];
  }

  /**
   * Get single decision from Ahjo API.
   *
   * @param string $id
   *   Native ID or Case ID.
   * @param string|null $query_string
   *   Query string to pass on.
   * @param bool $bypass_cache
   *   Bypass request cache.
   *
   * @return array
   *   Decision data inside 'decisions' to normalize output for migrations.
   */
  public function getSingleDecision(string $id, ?string $query_string, bool $bypass_cache = FALSE): array {
    if ($query_string === NULL) {
      $query_string = '';
    }
    $decisions_url = $this->getApiBaseUrl() . 'decisions/' . strtoupper($id) . '?' . urldecode($query_string);
    $decision = $this->getContent($decisions_url, $bypass_cache);

    // Single decisions are already inside an array.
    return ['decisions' => $decision];
  }

  /**
   * Get single record from Ahjo API.
   *
   * @param string $id
   *   Native ID.
   * @param string|null $query_string
   *   Query string to pass on.
   * @param bool $bypass_cache
   *   Bypass request cache.
   *
   * @return array
   *   Record data inside 'records' to normalize output for migrations.
   */
  public function getRecord(string $id, ?string $query_string, bool $bypass_cache = FALSE): array {
    if ($query_string === NULL) {
      $query_string = '';
    }
    $records_url = $this->getApiBaseUrl() . 'records/' . strtoupper($id) . '?' . urldecode($query_string);
    $record = $this->getContent($records_url, $bypass_cache);

    return ['records' => [$record]];
  }

  /**
   * Get single position of trust from Ahjo API.
   *
   * @param string $id
   *   Agent ID.
   * @param string|null $query_string
   *   Query string to pass on.
   * @param bool $bypass_cache
   *   Bypass request cache.
   *
   * @return array
   *   Trustee data inside 'trustees' to normalize output for migrations.
   */
  public function getSingleTrustee(string $id, ?string $query_string, bool $bypass_cache = FALSE): array {
    if ($query_string === NULL) {
      $query_string = '';
    }
    $agent_url = $this->getApiBaseUrl() . 'agents/positionoftrust/' . strtoupper($id) . '?' . urldecode($query_string);
    $agent = $this->getContent($agent_url, $bypass_cache);
    return ['trustees' => [$agent]];
  }

  /**
   * Get single organization from Ahjo API.
   *
   * @param string $id
   *   Organization ID.
   * @param string|null $query_string
   *   Query string to pass on.
   * @param bool $bypass_cache
   *   Bypass request cache.
   *
   * @return array
   *   Organization data inside 'decisionMakers' to normalize output.
   */
  public function getSingleOrganization(string $id, ?string $query_string, bool $bypass_cache = FALSE): array {
    if ($query_string === NULL) {
      $query_string = '';
    }
    $agent_url = $this->getApiBaseUrl() . 'organization?orgid=' . strtoupper($id) . '&' . urldecode($query_string);
    $org = $this->getContent($agent_url, $bypass_cache);
    return [
      'decisionMakers' => [
        ['Organization' => $org],
      ],
    ];
  }

  /**
   * Get organization positions of trust from Ahjo API.
   *
   * @param string $id
   *   Organization ID.
   * @param string|null $query_string
   *   Query string to pass on.
   * @param bool $bypass_cache
   *   Bypass request cache.
   *
   * @return array
   *   Positions of trust data.
   */
  public function getOrganizationPositions(string $id, ?string $query_string, bool $bypass_cache = FALSE): array {
    if ($query_string === NULL) {
      $query_string = '';
    }
    $positions_url = $this->getApiBaseUrl() . 'agents/positionoftrust?org=' . strtoupper($id) . '&' . urldecode($query_string);
    $data = $this->getContent($positions_url, $bypass_cache);
    return $data;
  }

  /**
   * Return organization chart structure.
   *
   * @param string $orgId
   *   Organization ID to start from.
   * @param int $steps
   *   Maximum levels to include in chart.
   * @param string $langcode
   *   Langcode for organization chart.
   *
   * @return array|null
   *   Structured array with organizations, or NULL if first org is not found.
   */
  public function getOrgChart(string $orgId, int $steps = 3, string $langcode = 'fi'): ?array {
    $query = \Drupal::entityQuery('node')
      ->condition('status', 1)
      ->range(0, 1)
      ->condition('field_policymaker_id', $orgId)
      ->condition('type', 'organization');

    $ids = $query->execute();
    if (empty($ids)) {
      return NULL;
    }

    $id = reset($ids);
    $node = Node::load($id);
    if (!$node instanceof NodeInterface) {
      return NULL;
    }

    if ($node->hasTranslation($langcode)) {
      $node = $node->getTranslation($langcode);
    }

    $data = $this->getOrgChartStructure($node, 0, $steps, $langcode);
    return [$data];
  }

  /**
   * Recursive method for getting organization structure.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Node to get child organizations for.
   * @param int $step
   *   Current level.
   * @param int $max_steps
   *   Maximum level.
   * @param string $langcode
   *   Langcode for organization chart.
   *
   * @return array
   *   Structured organization data.
   */
  protected function getOrgChartStructure(NodeInterface $node, int $step = 0, int $max_steps = 3, string $langcode = 'fi'): array {
    $data = [
      'Name' => $node->title->value,
      'ID' => $node->field_policymaker_id->value,
      'Language' => $node->langcode->value,
    ];

    if ($node->hasField('field_organization_data') && !$node->get('field_organization_data')->isEmpty()) {
      $org = json_decode($node->get('field_organization_data')->value, TRUE);
      $values = [
        'Type',
        'TypeId',
        'Sector',
        'Formed',
      ];
      foreach ($values as $value) {
        if (isset($org[$value])) {
          $data[$value] = $org[$value];
        }
      }
    }

    if ($node->field_org_level_below_ids->isEmpty() || $step >= $max_steps) {
      return $data;
    }

    $data['OrganizationLevelBelow'] = [];

    $orgs_below = [];

    foreach ($node->field_org_level_below_ids as $field) {
      $query = \Drupal::entityQuery('node')
        ->condition('status', 1)
        ->range(0, 1)
        ->condition('field_policymaker_id', $field->value)
        ->condition('type', 'organization');

      $ids = $query->execute();
      if (empty($ids)) {
        continue;
      }

      $id = reset($ids);
      $child_node = Node::load($id);
      if ($child_node instanceof NodeInterface) {
        if ($child_node->hasTranslation($langcode)) {
          $child_node = $child_node->getTranslation($langcode);
        }
        $orgs_below[] = $this->getOrgChartStructure($child_node, $step + 1, $max_steps, $langcode);
      }
    }

    $data['OrganizationLevelBelow'] = $orgs_below;
    return $data;
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

      case 'meetings_cancelled':
        $filename = 'meetings_cancelled.json';
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

      case 'resolutions_all':
        $filename = 'resolutions_all.json';
        break;

      case 'resolutions_latest':
        $filename = 'resolutions_latest.json';
        break;

      case 'initiatives_all':
        $filename = 'initiatives_all.json';
        break;

      case 'initiatives_latest':
        $filename = 'initiatives_latest.json';
        break;

      case 'positionsoftrust':
        $filename = 'positionsoftrust.json';
        break;

      case 'positionsoftrust_council':
        $filename = 'positionsoftrust_council.json';
        break;

      case 'trustees':
        $filename = 'trustees.json';
        break;

      case 'trustees_fi':
        $filename = 'trustees_fi.json';
        break;

      case 'trustees_sv':
        $filename = 'trustees_sv.json';
        break;

      case 'trustees_council':
        $filename = 'trustees_council.json';
        break;

      case 'trustees_council_fi':
        $filename = 'trustees_council_fi.json';
        break;

      case 'trustees_council_sv':
        $filename = 'trustees_council_sv.json';
        break;

      case 'decisionmakers':
        $filename = 'decisionmakers.json';
        break;

      case 'decisionmakers_sv':
        $filename = 'decisionmakers_sv.json';
        break;

      case 'decisionmakers_latest':
        $filename = 'decisionmakers_latest.json';
        break;

      case 'decisionmakers_latest_sv':
        $filename = 'decisionmakers_latest_sv.json';
        break;

      case 'callback_test':
        $filename = 'callback_test.json';
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
    if (!empty($data['item_id'])) {
      $context['message'] = 'Importing item number ' . $data['count'] . ' with ID: ' . $data['item_id'];
    }
    else {
      $context['message'] = 'Importing item number ' . $data['count'];
    }

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

    // Check if ID is blacklisted.
    $disallowed_ids = $ahjo_proxy->getBlacklistedIds();
    if (in_array($data['item_id'], $disallowed_ids)) {
      $context['results']['failed'][] = $data['item'];
      return;
    }

    $full_data = $ahjo_proxy->getFullContentForItem($data['item']);

    if (!empty($full_data)) {
      $context['results']['items'][] = $full_data;
    }
    else {
      // Add failed items to callback queue so they can be retried later.
      if (!empty($data['endpoint']) && !empty($data['item_id'])) {
        $ahjo_proxy->addItemToAhjoQueue($data['endpoint'], $data['item_id']);
      }

      // Mark as failed.
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

    $ahjo_proxy = \Drupal::service('paatokset_ahjo_proxy');
    $ahjo_proxy->fileRepository->writeData(json_encode([$results['list_key'] => $results['items']]), 'public://' . $filename, FileSystemInterface::EXISTS_REPLACE);
    $messenger->addMessage('Aggregated data saved into public://' . $filename);

    // Save failed array into filesystem even if it's empty so we can wipe it.
    $ahjo_proxy->fileRepository->writeData(json_encode($results['failed']), 'public://failed_' . $filename, FileSystemInterface::EXISTS_REPLACE);
    if (!empty($results['failed'])) {
      $messenger->addMessage('Data for failed items saved into public://failed_' . $filename);
    }
  }

  /**
   * Static callback for aggregating groups to get all positions of trust.
   *
   * @param mixed $data
   *   Data for operation.
   * @param mixed $context
   *   Context for batch operation.
   */
  public static function processGroupItem($data, &$context) {
    $context['message'] = 'Importing item number ' . $data['count'];

    if (!isset($context['results']['starttime'])) {
      $context['results']['starttime'] = microtime(TRUE);
    }
    if (!isset($context['results']['items'])) {
      $context['results']['items'] = [];
    }
    if (!isset($context['results']['failed'])) {
      $context['results']['failed'] = [];
    }
    if (!isset($context['results']['filename']) && isset($data['filename'])) {
      $context['results']['filename'] = $data['filename'];
    }

    /** @var \Drupal\paatokset_ahjo_proxy\AhjoProxy $ahjo_proxy */
    $ahjo_proxy = \Drupal::service('paatokset_ahjo_proxy');
    $full_data = $ahjo_proxy->getData($data['endpoint'], $data['query_string']);

    if (!empty($full_data)) {
      $context['results']['items'][] = $full_data;
    }
    else {
      $context['results']['failed'][] = $data;
    }
  }

  /**
   * Static callback function for finishing group aggregation batch.
   *
   * @param mixed $success
   *   If batch succeeded or not.
   * @param array $results
   *   Aggregated results.
   * @param array $operations
   *   Operations with errors.
   */
  public static function finishGroups($success, array $results, array $operations) {
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
      $filename = 'positionsoftrust.json';
    }

    /** @var \Drupal\paatokset_ahjo_proxy\AhjoProxy $ahjo_proxy */
    $ahjo_proxy = \Drupal::service('paatokset_ahjo_proxy');
    $ahjo_proxy->fileRepository->writeData(json_encode($results['items']), 'public://' . $filename, FileSystemInterface::EXISTS_REPLACE);
    $messenger->addMessage('Aggregated data saved into public://' . $filename);

    // Save failed array into filesystem even if it's empty so we can wipe it.
    $ahjo_proxy->fileRepository->writeData(json_encode($results['failed']), 'public://failed_' . $filename, FileSystemInterface::EXISTS_REPLACE);
    if (!empty($results['failed'])) {
      $messenger->addMessage('Data for failed items saved into public://failed_' . $filename);
    }
  }

  /**
   * Static callback for aggregating individual positions of trust.
   *
   * @param mixed $data
   *   Data for operation.
   * @param mixed $context
   *   Context for batch operation.
   */
  public static function processTrusteeItem($data, &$context) {
    $context['message'] = 'Importing item number ' . $data['count'];

    if (!isset($context['results']['starttime'])) {
      $context['results']['starttime'] = microtime(TRUE);
    }
    if (!isset($context['results']['items'])) {
      $context['results']['items'] = [];
    }
    if (!isset($context['results']['failed'])) {
      $context['results']['failed'] = [];
    }
    if (!isset($context['results']['filename']) && isset($data['filename'])) {
      $context['results']['filename'] = $data['filename'];
    }
    if (!isset($context['results']['langcode']) && isset($data['langcode'])) {
      $context['results']['langcode'] = $data['langcode'];
    }

    /** @var \Drupal\paatokset_ahjo_proxy\AhjoProxy $ahjo_proxy */
    $ahjo_proxy = \Drupal::service('paatokset_ahjo_proxy');
    $query_string = NULL;
    if (!empty($data['langcode'])) {
      $query_string = 'apireqlang=' . $data['langcode'];
    }
    $full_data = $ahjo_proxy->getData($data['endpoint'], $query_string);

    if (!empty($full_data)) {
      $context['results']['items'][] = $full_data;
    }
    else {
      $context['results']['failed'][] = $data;
    }
  }

  /**
   * Static callback function for finishing group aggregation batch.
   *
   * @param mixed $success
   *   If batch succeeded or not.
   * @param array $results
   *   Aggregated results.
   * @param array $operations
   *   Operations with errors.
   */
  public static function finishTrustees($success, array $results, array $operations) {
    $messenger = \Drupal::messenger();
    $total = count($results['items']);

    $end_time = microtime(TRUE);
    $total_time = ($end_time - $results['starttime']);
    $messenger->addMessage('Processed ' . $total . ' items in ' . $total_time . ' seconds.');
    $messenger->addMessage('Items failed: ' . count($results['failed']));

    if (!empty($results['filename'])) {
      $filename = $results['filename'];
    }
    elseif (!empty($results['langcode'])) {
      $filename = 'trustees_' . $results['langcode'] . '.json';
    }
    else {
      $filename = 'trustees.json';
    }

    /** @var \Drupal\paatokset_ahjo_proxy\AhjoProxy $ahjo_proxy */
    $ahjo_proxy = \Drupal::service('paatokset_ahjo_proxy');
    $ahjo_proxy->fileRepository->writeData(json_encode(['trustees' => $results['items']]), 'public://' . $filename, FileSystemInterface::EXISTS_REPLACE);
    $messenger->addMessage('Aggregated data saved into public://' . $filename);

    // Save failed array into filesystem even if it's empty so we can wipe it.
    $ahjo_proxy->fileRepository->writeData(json_encode($results['failed']), 'public://failed_' . $filename, FileSystemInterface::EXISTS_REPLACE);
    if (!empty($results['failed'])) {
      $messenger->addMessage('Data for failed items saved into public://failed_' . $filename);
    }
  }

  /**
   * Static callback function for processing decision data.
   *
   * @param mixed $data
   *   Data for operation.
   * @param mixed $context
   *   Context for batch operation.
   */
  public static function processDecisionItem($data, &$context) {
    $messenger = \Drupal::messenger();
    $context['message'] = 'Importing item number ' . $data['count'];

    if (!isset($context['results']['items'])) {
      $context['results']['items'] = [];
    }
    if (!isset($context['results']['failed'])) {
      $context['results']['failed'] = [];
    }
    if (!isset($context['results']['starttime'])) {
      $context['results']['starttime'] = microtime(TRUE);
    }

    /** @var \Drupal\paatokset_ahjo_proxy\AhjoProxy $ahjo_proxy */
    $ahjo_proxy = \Drupal::service('paatokset_ahjo_proxy');
    $node = Node::load($data['nid']);

    // Fetch record content from endpoint.
    $record_content = NULL;
    $fetch_record_from_case = FALSE;
    if ($data['endpoint']) {
      $record_content = $ahjo_proxy->getData($data['endpoint'], NULL);
    }
    elseif ($node->hasField('field_decision_record') && !$node->get('field_decision_record')->isEmpty()) {
      $record_content = json_decode($node->get('field_decision_record')->value, TRUE);
    }

    // Local data is formatted a bit differently.
    if (isset($record_content['records'])) {
      $record_content = $record_content['records'][0];
    }

    // Update decision history if the field is empty.
    if (!empty($data['decision_endpoint']) && $node->hasField('field_decision_history') && $node->get('field_decision_history')->isEmpty()) {
      $ahjo_proxy->updateDecisionHistoryContent($node, $data['decision_endpoint']);
    }

    // If language is set to outdated, refetch organization data.
    if ($node->hasField('field_record_language_checked') && !$node->get('field_record_language_checked')->value) {
      $recheck_language = TRUE;
    }
    else {
      $recheck_language = FALSE;
    }

    // Fetch record content and reset langcode.
    if (!empty($record_content)) {
      $ahjo_proxy->updateDecisionRecordData($node, $record_content);
    }
    else {
      $messenger->addMessage('Could not fetch record for nid: ' . $node->id());
      $fetch_record_from_case = TRUE;
    }

    // Fetch meeting date for decision.
    if ($data['meeting_id']) {
      $ahjo_proxy->updateDecisionMeetingData($node, $data['meeting_id']);
    }

    // Fetch case data for decision.
    // If record couldn't be fetched from endpoint, try to get it from case.
    if ($data['case_id']) {
      $ahjo_proxy->updateDecisionCaseData($node, $data['case_id'], $fetch_record_from_case);
    }

    // If language was set to outdated, refetch some translatable fields.
    if ($recheck_language) {
      $ahjo_proxy->updateDecisionTranslatedFields($node, $data['decision_endpoint']);
    }

    // If meeting date can't be set, use a default value.
    if ($node->get('field_meeting_date')->isEmpty()) {
      $node->set('field_meeting_date', '2001-01-01T00:00:00');
    }

    // If decision date can't be set, use a default value.
    if ($node->get('field_decision_date')->isEmpty()) {
      $messenger->addMessage('Decision date fetching failed for decision with nid: ' . $node->id());
      $node->set('field_decision_date', '2001-01-01T00:00:00');
      $context['results']['failed'][] = $node->id();
    }
    else {
      // Consider this successfull if date was set.
      $context['results']['items'][] = $node->id();
    }

    $node->save();
  }

  /**
   * Update decision history from endpoint.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Node to update.
   * @param string $endpoint
   *   Endpoint to get data from.
   */
  protected function updateDecisionHistoryContent(NodeInterface &$node, string $endpoint): void {
    /** @var \Drupal\paatokset_ahjo_proxy\AhjoProxy $ahjo_proxy */
    $ahjo_proxy = \Drupal::service('paatokset_ahjo_proxy');
    $content = $ahjo_proxy->getData($endpoint, NULL);

    // Local data is formatted a bit differently.
    if (!empty($content['decisions'])) {
      $content = $content['decisions'][0];
    }
    // Single decisions from endpoint still come as an array.
    else {
      $content = reset($content);
    }

    if (empty($content)) {
      return;
    }

    if (!empty($content['DecisionHistoryHTML'])) {
      $node->set('field_decision_history', [
        'value' => $content['DecisionHistoryHTML'],
        'format' => 'plain_text',
      ]);
    }
    if (!empty($content['DecisionHistoryPDF'])) {
      $node->set('field_decision_history_pdf', json_encode($content['DecisionHistoryPDF']));
    }
  }

  /**
   * Update various translated fields that require fetching with apireqlang.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Node to update.
   * @param string $endpoint
   *   Endpoint to get data from.
   */
  protected function updateDecisionTranslatedFields(NodeInterface &$node, string $endpoint): void {
    $langcode = $node->get('langcode')->value;
    if (!in_array($langcode, ['fi', 'sv'])) {
      $langcode = 'fi';
    }
    $query_string = 'apireqlang=' . $langcode;

    /** @var \Drupal\paatokset_ahjo_proxy\AhjoProxy $ahjo_proxy */
    $ahjo_proxy = \Drupal::service('paatokset_ahjo_proxy');
    $content = $ahjo_proxy->getData($endpoint, $query_string);

    // Local data is formatted a bit differently.
    if (!empty($content['decisions'])) {
      $content = $content['decisions'][0];
    }
    // Single decisions from endpoint still come as an array.
    else {
      $content = reset($content);
    }

    if (empty($content)) {
      return;
    }

    if (!empty($content['Organization'])) {
      $node->set('field_decision_organization', json_encode($content['Organization']));
    }
    else {
      return;
    }

    if (!empty($content['Organization']['Name'])) {
      $node->set('field_dm_org_name', $content['Organization']['Name']);
    }

    if (empty($content['Organization']['OrganizationLevelAbove']) || empty($content['Organization']['OrganizationLevelAbove']['organizations'][0])) {
      return;
    }

    if (!empty($content['Organization']['OrganizationLevelAbove']['organizations'][0]['Name'])) {
      $node->set('field_dm_org_above_name', $content['Organization']['OrganizationLevelAbove']['organizations'][0]['Name']);
    }
  }

  /**
   * Update decision node based on record data.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Decision node.
   * @param array $record_content
   *   Record data.
   */
  protected function updateDecisionRecordData(NodeInterface &$node, array $record_content): void {
    $node->set('field_decision_record', json_encode($record_content));

    // If this is a decision (not a motion), set outdated flag to false.
    if ($node->get('field_is_decision')->value) {
      $node->set('field_outdated_document', 0);
    }

    // Set record Series ID, if found.
    if (!empty($record_content['VersionSeriesId'])) {
      $node->set('field_decision_series_id', $record_content['VersionSeriesId']);
    }

    if (isset($record_content['Issued'])) {
      $date = new \DateTime($record_content['Issued'], new \DateTimeZone('Europe/Helsinki'));
      $date->setTimezone(new \DateTimeZone('UTC'));
      $node->set('field_decision_date', $date->format('Y-m-d\TH:i:s'));
    }

    $enabled_languages = ['fi', 'sv'];
    if (isset($record_content['Language']) && in_array($record_content['Language'], $enabled_languages)) {
      $node->set('field_record_language_checked', 1);
      $node->set('langcode', $record_content['Language']);
    }

    $unique_id = '';
    if (!$node->get('field_diary_number')->isEmpty()) {
      $unique_id .= $node->get('field_diary_number')->value . '-';
    }
    else {
      $unique_id .= '0-';
    }
    if (isset($record_content['MeetingID'])) {
      $unique_id .= $record_content['MeetingID'] . '-';
    }
    elseif (!$node->get('field_meeting_id')->isEmpty()) {
      $unique_id .= $node->get('field_meeting_id')->value . '-';
    }
    else {
      $unique_id .= '0-';
    }
    if (!$node->get('field_decision_section')->isEmpty()) {
      $unique_id .= $node->get('field_decision_section')->value . '-';
    }
    else {
      $unique_id .= '0-';
    }
    if (isset($record_content['AgendaPoint'])) {
      $unique_id .= $record_content['AgendaPoint'] . '-';
    }
    else {
      $unique_id .= '0-';
    }
    if (!$node->get('field_policymaker_id')->isEmpty()) {
      $unique_id .= $node->get('field_policymaker_id')->value;
    }
    else {
      $unique_id .= '0';
    }
    $node->set('field_unique_id', $unique_id);
  }

  /**
   * Update decision node based on case node data.
   *
   * @param Drupal\node\NodeInterface $node
   *   Decision node.
   * @param string $case_id
   *   Case diary number.
   * @param bool $set_record
   *   Set decision record and issued date from case node.
   */
  protected function updateDecisionCaseData(NodeInterface &$node, string $case_id, bool $set_record = FALSE): void {
    $messenger = \Drupal::messenger();
    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', 'case')
      ->condition('status', 1)
      ->condition('field_diary_number', $case_id)
      ->range('0', 1)
      ->latestRevision();
    $nids = $query->execute();

    if (empty($nids)) {
      $messenger->addMessage('Case not found: ' . $case_id);
      $this->addItemToAhjoQueue('cases', $case_id);
      return;
    }

    $nid = array_shift($nids);
    $case = Node::load($nid);

    if (!$case instanceof NodeInterface) {
      return;
    }

    // If case doesn't have a title, just reuse own title.
    if ($case->hasField('field_no_title_for_case') && $case->get('field_no_title_for_case')->value) {
      $node->set('field_decision_case_title', $node->field_full_title->value);
    }
    else {
      $node->set('field_decision_case_title', $case->field_full_title->value);
    }

    if (!$set_record) {
      return;
    }

    $decision_id = $node->field_decision_native_id->value;
    $record_content = NULL;
    foreach ($case->get('field_case_records') as $field) {
      $data = json_decode($field->value, TRUE);
      if ($data['NativeId'] === $decision_id) {
        $record_content = $data;
        break;
      }
    }

    if (!empty($record_content)) {
      $node->set('field_decision_record', json_encode($record_content));
    }

    if (isset($record_content['Issued'])) {
      $date = new \DateTime($record_content['Issued'], new \DateTimeZone('Europe/Helsinki'));
      $date->setTimezone(new \DateTimeZone('UTC'));
      $node->set('field_decision_date', $date->format('Y-m-d\TH:i:s'));
    }
  }

  /**
   * Update decision node based on meeting node data.
   *
   * @param Drupal\node\NodeInterface $node
   *   Decision node.
   * @param string $meeting_id
   *   Meeting ID.
   */
  protected function updateDecisionMeetingData(NodeInterface &$node, string $meeting_id): void {
    $messenger = \Drupal::messenger();
    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', 'meeting')
      ->condition('status', 1)
      ->condition('field_meeting_id', $meeting_id)
      ->range('0', 1)
      ->latestRevision();
    $nids = $query->execute();

    if (empty($nids)) {
      $messenger->addMessage('Meeting not found: ' . $meeting_id);
      $this->addItemToAhjoQueue('meetings', $meeting_id);
      return;
    }

    $nid = array_shift($nids);
    $meeting = Node::load($nid);

    if (!$meeting instanceof NodeInterface) {
      return;
    }

    $node->set('field_meeting_date', $meeting->field_meeting_date->value);
    $node->set('field_meeting_sequence_number', $meeting->field_meeting_sequence_number->value);
  }

  /**
   * Static callback function for processing case data.
   *
   * @param mixed $data
   *   Data for operation.
   * @param mixed $context
   *   Context for batch operation.
   */
  public static function processCaseItem($data, &$context) {
    $messenger = \Drupal::messenger();
    $context['message'] = 'Importing item number ' . $data['count'];

    if (!isset($context['results']['items'])) {
      $context['results']['items'] = [];
    }
    if (!isset($context['results']['failed'])) {
      $context['results']['failed'] = [];
    }
    if (!isset($context['results']['starttime'])) {
      $context['results']['starttime'] = microtime(TRUE);
    }

    /** @var \Drupal\paatokset_ahjo_proxy\AhjoProxy $ahjo_proxy */
    $ahjo_proxy = \Drupal::service('paatokset_ahjo_proxy');
    $node = Node::load($data['nid']);

    if ($node->bundle() !== 'case') {
      return;
    }

    // Fetch updated content from endpoint.
    $content = $ahjo_proxy->getData($data['endpoint'], NULL);

    // Local and proxy data is formatted a bit differently than API data.
    if (isset($content['cases'])) {
      $content = $content['cases'][0];
    }

    if (!empty($content)) {
      $node->set('field_publicity_class', $content['PublicityClass']);
      $node->set('field_security_reasons', $content['SecurityReasons']);

      if (!empty($data['decision_endpoint']) && $node->hasField('field_no_title_for_case') && $node->get('field_no_title_for_case')->value) {
        $ahjo_proxy->updateCaseTitleFromDecision($node, $data['decision_endpoint']);
      }

      $node->save();
      $context['results']['items'][] = $node->id();
    }
    else {
      $messenger->addMessage('Could not fetch content for nid: ' . $node->id());
      $context['results']['failed'][] = $node->id();
    }
  }

  /**
   * Update case title from latest decision.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Case node to update.
   * @param string $endpoint
   *   Decision endpoint to get data from.
   */
  protected function updateCaseTitleFromDecision(NodeInterface &$node, string $endpoint): void {
    // Fetch decision content from endpoint.
    $ahjo_proxy = \Drupal::service('paatokset_ahjo_proxy');
    $content = $ahjo_proxy->getData($endpoint, NULL);

    // Local and proxy data is formatted a bit differently than API data.
    if (isset($content['decisions'])) {
      $content = $content['decisions'][0];
    }
    // Single decisions from endpoint still come as an array.
    else {
      $content = reset($content);
    }

    if (empty($content)) {
      return;
    }
    if (empty($content['Title'])) {
      return;
    }

    $title = $content['Title'];
    $truncated_title = Unicode::truncate($title, 255, TRUE, TRUE);
    $node->set('title', $truncated_title);
    $node->set('field_full_title', $title);
  }

  /**
   * Static callback function for processing decision date data.
   *
   * @param mixed $data
   *   Data for operation.
   * @param mixed $context
   *   Context for batch operation.
   */
  public static function updateDecisionDate($data, &$context) {
    $messenger = \Drupal::messenger();
    $context['message'] = 'Importing item number ' . $data['count'];

    if (!isset($context['results']['items'])) {
      $context['results']['items'] = [];
    }
    if (!isset($context['results']['failed'])) {
      $context['results']['failed'] = [];
    }
    if (!isset($context['results']['starttime'])) {
      $context['results']['starttime'] = microtime(TRUE);
    }

    /** @var \Drupal\paatokset_ahjo_proxy\AhjoProxy $ahjo_proxy */
    $ahjo_proxy = \Drupal::service('paatokset_ahjo_proxy');
    $node = Node::load($data['nid']);

    // Fetch decision content from endpoint.
    $content = NULL;
    if ($data['endpoint']) {
      $content = $ahjo_proxy->getData($data['endpoint'], NULL);
    }

    // Local data is formatted a bit differently.
    if (!empty($content['decisions'])) {
      $content = $content['decisions'][0];
    }
    // Single decisions from endpoint still come as an array.
    else {
      $content = reset($content);
    }

    if (!empty($content)) {

      if (!empty($content['PDF']) && !empty($content['PDF']['Issued'])) {
        $issued_date = new \DateTime($content['PDF']['Issued'], new \DateTimeZone('Europe/Helsinki'));
        $issued_date->setTimezone(new \DateTimeZone('UTC'));
      }
      if (!empty($content['Meeting']) && !empty($content['Meeting']['DateMeeting'])) {
        $decision_date = new \DateTime($content['Meeting']['DateMeeting'], new \DateTimeZone('Europe/Helsinki'));
        $decision_date->setTimezone(new \DateTimeZone('UTC'));
      }
      elseif (!empty($content['DateDecision'])) {
        $decision_date = new \DateTime($content['DateDecision'], new \DateTimeZone('Europe/Helsinki'));
        $decision_date->setTimezone(new \DateTimeZone('UTC'));
      }

      if ($issued_date) {
        $node->set('field_decision_date', $issued_date->format('Y-m-d\TH:i:s'));
        $node->set('field_dates_checked', 1);
      }
      // Only save node if decision date could be fetched.
      if ($decision_date) {
        $node->set('field_meeting_date', $decision_date->format('Y-m-d\TH:i:s'));
        $node->set('field_dates_checked', 1);
        $node->save();
      }
      $context['results']['items'][] = $node->id();
    }
    else {
      $messenger->addMessage('Could not fetch dates for for nid: ' . $node->id());
      $context['results']['failed'][] = $node->id();
    }
  }

  /**
   * Static callback function for checking decision publication status.
   *
   * @param mixed $data
   *   Data for operation.
   * @param mixed $context
   *   Context for batch operation.
   */
  public static function checkDecisionStatus($data, &$context) {
    $messenger = \Drupal::messenger();
    $context['message'] = 'Checking item number ' . $data['count'] . ' (nid:' . $data['nid'] . ')';

    if (!isset($context['results']['items'])) {
      $context['results']['items'] = [];
    }
    if (!isset($context['results']['failed'])) {
      $context['results']['failed'] = [];
    }
    if (!isset($context['results']['starttime'])) {
      $context['results']['starttime'] = microtime(TRUE);
    }

    /** @var \Drupal\paatokset_ahjo_proxy\AhjoProxy $ahjo_proxy */
    $ahjo_proxy = \Drupal::service('paatokset_ahjo_proxy');
    $node = Node::load($data['nid']);

    // Fetch decision content from endpoint.
    $content = NULL;
    if ($data['endpoint']) {
      $content = $ahjo_proxy->getData($data['endpoint'], NULL);
    }

    // Local data is formatted a bit differently.
    if (!empty($content['decisions'])) {
      $content = $content['decisions'][0];
    }
    // Single decisions from endpoint still come as an array.
    else {
      $content = reset($content);
    }

    $unpublish = FALSE;
    if (!empty($content)) {
      // Unpublish if fileURI is missing.
      if (empty($content['PDF']) || empty($content['PDF']['FileURI'])) {
        $unpublish = TRUE;
      }
      // Unpublish if content and motion fields are empty.
      if (empty($content['Content']) && empty($content['Motion'])) {
        $unpublish = TRUE;
      }
      if ($unpublish) {
        $messenger->addMessage('Status not OK, unpublishing nid: ' . $node->id());
        $node->set('status', 0);
      }
      $node->set('field_status_checked', 1);
      $node->save();
      $context['results']['items'][] = $node->id();
    }
    else {
      $messenger->addMessage('Could not fetch dates for for nid: ' . $node->id());
      $context['results']['failed'][] = $node->id();
    }
  }

  /**
   * Static callback function for processing decision attachment data.
   *
   * @param mixed $data
   *   Data for operation.
   * @param mixed $context
   *   Context for batch operation.
   */
  public static function updateDecisionAttachments($data, &$context) {
    $messenger = \Drupal::messenger();
    $context['message'] = 'Importing item number ' . $data['count'];

    if (!isset($context['results']['items'])) {
      $context['results']['items'] = [];
    }
    if (!isset($context['results']['failed'])) {
      $context['results']['failed'] = [];
    }
    if (!isset($context['results']['starttime'])) {
      $context['results']['starttime'] = microtime(TRUE);
    }

    /** @var \Drupal\paatokset_ahjo_proxy\AhjoProxy $ahjo_proxy */
    $ahjo_proxy = \Drupal::service('paatokset_ahjo_proxy');
    $node = Node::load($data['nid']);

    if (empty($data['endpoint'])) {
      $messenger->addMessage('Could not fetch attachments for for nid: ' . $node->id());
      $context['results']['failed'][] = $node->id();
      return;
    }

    // Fetch decision content from endpoint.
    $content = $ahjo_proxy->getData($data['endpoint'], NULL);

    // Local data is formatted a bit differently.
    if (isset($content['decisions'])) {
      $content = $content['decisions'][0];
    }
    // Single decisions from endpoint still come as an array.
    else {
      $content = reset($content);
    }

    if (!empty($content)) {
      $attachments = [];
      if (!empty($content['Attachments'])) {
        foreach ($content['Attachments'] as $attachment) {
          $attachments[] = json_encode($attachment);
        }
      }
      $node->set('field_decision_attachments', $attachments);
      $node->set('field_attachments_checked', 1);
      $node->save();
      $context['results']['items'][] = $node->id();
    }
    else {
      $messenger->addMessage('Could not fetch attachments for for nid: ' . $node->id());
      $context['results']['failed'][] = $node->id();
    }
  }

  /**
   * Static callback function for checking decisionmaker status.
   *
   * @param mixed $data
   *   Data for operation.
   * @param mixed $context
   *   Context for batch operation.
   */
  public static function processDmStatusCheck($data, &$context) {
    $messenger = \Drupal::messenger();

    if (!isset($context['results']['items'])) {
      $context['results']['items'] = [];
    }
    if (!isset($context['results']['failed'])) {
      $context['results']['failed'] = [];
    }
    if (!isset($context['results']['starttime'])) {
      $context['results']['starttime'] = microtime(TRUE);
    }

    /** @var \Drupal\paatokset_ahjo_proxy\AhjoProxy $ahjo_proxy */
    $ahjo_proxy = \Drupal::service('paatokset_ahjo_proxy');
    $node = Node::load($data['nid']);

    if ($node->bundle() !== 'policymaker') {
      return;
    }

    $context['message'] = 'Checking decisionmaker with ID: ' . $node->field_policymaker_id->value . ', operation: ' . $data['count'];

    // Fetch updated content from endpoint.
    $content = $ahjo_proxy->getData($data['endpoint'], $data['endpoint_query_string']);

    // Local and proxy data is formatted a bit differently than API data.
    if (isset($content['decisionMakers'][0]['Organization'])) {
      $content = $content['decisionMakers'][0]['Organization'];
    }

    if (empty($content)) {
      $messenger->addMessage('Could not fetch data for Org ID ' . $data['org_id'] . ' (nid: ' . $node->id() . ')');
      $context['results']['failed'][] = $node->id();
    }
    else {
      $context['results']['items'][] = $node->id();
      if (isset($content['Existing']) && $content['Existing'] === 'false') {
        $messenger->addMessage('Found inactive organization with Org ID ' . $data['org_id'] . '(nid:' . $node->id() . ')');
        $node->set('field_policymaker_existing', 0);
        $node->save();
      }
    }
  }

  /**
   * Add entity to callback queue.
   *
   * @param string $endpoint
   *   Endpoint to use (cases, meetings, decisions).
   * @param string $id
   *   Case diary number, meeting or decision ID.
   * @param string $queue_name
   *   Queue to add entity to. Defaults to 'ahjo_api_retry_queue'.
   * @param string $update_type
   *   Update type (for debugging purposes). Defaults to 'AddedFromDrush'.
   *
   * @return string|null
   *   Item ID if it was successfully added to queue, otherwise NULL.
   */
  public function addItemToAhjoQueue(string $endpoint, string $id, string $queue_name = 'ahjo_api_retry_queue', $update_type = 'AddedFromDrush'): ?string {
    // Attempt to reduce duplicates.
    if ($this->checkIfItemIsAlreadyInQueue($endpoint, $id, $queue_name)) {
      return NULL;
    }
    $created = (int) (new \DateTime('NOW'))->format('U');
    $queue = \Drupal::service('queue')->get($queue_name);
    $item_id = $queue->createItem([
      'id' => $endpoint,
      'content' => (object) [
        'updatetype' => $update_type,
        'id' => $id,
      ],
      'created' => $created,
      'request' => [],
    ]);

    return $item_id;
  }

  /**
   * Check if item has already been added to queue. Used to reduce duplicates.
   *
   * @param string $endpoint
   *   Entity type / endpoint for item.
   * @param string $id
   *   Item's ID in Ahjo API.
   * @param string $queue_name
   *   Queue name to check.
   *
   * @return bool
   *   Returns TRUE if item is found in queue.
   */
  public function checkIfItemIsAlreadyInQueue(string $endpoint, string $id, string $queue_name): bool {
    // Load the specified queue item from the queue table.
    $query = $this->database->select('queue', 'q')
      ->fields('q', ['item_id'])
      ->condition('q.name', $queue_name)
      ->condition('q.data', '%' . $this->database->escapeLike($id) . '%', 'LIKE')
      ->condition('q.data', '%' . $this->database->escapeLike($endpoint) . '%', 'LIKE')
      // Item id should be unique.
      ->range(0, 1);

    if ($query->execute()->fetchObject()) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Static callback function for processing decision data.
   *
   * @param mixed $data
   *   Data for operation.
   * @param mixed $context
   *   Context for batch operation.
   */
  public static function parseDecisionItem($data, &$context) {
    $messenger = \Drupal::messenger();
    $context['message'] = 'Parsing item number ' . $data['count'];

    if (!isset($context['results']['items'])) {
      $context['results']['items'] = [];
    }
    if (!isset($context['results']['failed'])) {
      $context['results']['failed'] = [];
    }
    if (!isset($context['results']['starttime'])) {
      $context['results']['starttime'] = microtime(TRUE);
    }

    /** @var \Drupal\paatokset_ahjo_api\Service\CaseService $caseService */
    $caseService = \Drupal::service('paatokset_ahjo_cases');
    $node = Node::load($data['nid']);

    $decision_content = $caseService->getDecisionContentFromHtml($node, 'field_decision_content');
    if ($node->hasField('field_decision_content_parsed')) {
      $node->set('field_decision_content_parsed', $decision_content);
    }

    $decision_motion = $caseService->getDecisionContentFromHtml($node, 'field_decision_motion');
    if ($node->hasField('field_decision_motion_parsed')) {
      $node->set('field_decision_motion_parsed', $decision_motion);
    }

    // If both decision and motion content can't be set, consider this failed.
    if ($node->get('field_decision_content_parsed')->isEmpty() && $node->get('field_decision_motion_parsed')->isEmpty()) {
      $messenger->addMessage('Content parsing failed for decision with nid: ' . $data['nid']);
      $context['results']['failed'][] = $node->id();
    }
    else {
      $context['results']['items'][] = $node->id();
      $node->save();
    }
  }

  /**
   * Parse "is decision" flag for nodes where it is missing.
   *
   * @param mixed $data
   *   Data for operation.
   * @param mixed $context
   *   Context for batch operation.
   */
  public static function setDecisionItemFlag($data, &$context) {
    $context['message'] = 'Parsing item number ' . $data['count'];

    if (!isset($context['results']['items'])) {
      $context['results']['items'] = [];
    }
    if (!isset($context['results']['failed'])) {
      $context['results']['failed'] = [];
    }
    if (!isset($context['results']['starttime'])) {
      $context['results']['starttime'] = microtime(TRUE);
    }

    $node = Node::load($data['nid']);
    if ($node->hasField('field_is_decision')) {
      $context['results']['items'][] = $node->id();
      $node->set('field_is_decision', TRUE);
      $node->save();
    }
    else {
      $context['results']['failed'][] = $node->id();
    }
  }

  /**
   * Reset description fields from policymaker nodes.
   *
   * @param mixed $data
   *   Data for operation.
   * @param mixed $context
   *   Context for batch operation.
   */
  public static function removePolicyMakerFieldsFromItem($data, &$context) {
    $context['message'] = 'Parsing item number ' . $data['count'];

    if (!isset($context['results']['items'])) {
      $context['results']['items'] = [];
    }
    if (!isset($context['results']['failed'])) {
      $context['results']['failed'] = [];
    }
    if (!isset($context['results']['starttime'])) {
      $context['results']['starttime'] = microtime(TRUE);
    }

    $node = Node::load($data['nid']);

    $reset_fields = [
      'field_documents_description',
      'field_recording_description',
      'field_meetings_description',
      'field_decisions_description',
    ];

    $success = FALSE;
    foreach ($reset_fields as $field) {
      if ($node->hasField($field)) {
        $success = TRUE;
        $node->set($field, NULL);
      }
    }

    if ($success) {
      $context['results']['items'][] = $node->id();
      $node->save();
    }
    else {
      $context['results']['failed'][] = $node->id();
    }
  }

  /**
   * Reset unique ID field for decision nodes.
   *
   * @param mixed $data
   *   Data for operation.
   * @param mixed $context
   *   Context for batch operation.
   */
  public static function removeUniqueIdFromItem($data, &$context) {
    $context['message'] = 'Parsing item number ' . $data['count'];

    if (!isset($context['results']['items'])) {
      $context['results']['items'] = [];
    }
    if (!isset($context['results']['failed'])) {
      $context['results']['failed'] = [];
    }
    if (!isset($context['results']['starttime'])) {
      $context['results']['starttime'] = microtime(TRUE);
    }

    $node = Node::load($data['nid']);

    $reset_fields = [
      'field_unique_id',
    ];

    $success = FALSE;
    foreach ($reset_fields as $field) {
      if ($node->hasField($field)) {
        $success = TRUE;
        $node->set($field, NULL);
      }
    }

    if ($success) {
      $context['results']['items'][] = $node->id();
      $node->save();
    }
    else {
      $context['results']['failed'][] = $node->id();
    }
  }

  /**
   * Static callback function for finishing group aggregation batch.
   *
   * @param mixed $success
   *   If batch succeeded or not.
   * @param array $results
   *   Aggregated results.
   * @param array $operations
   *   Operations with errors.
   */
  public static function finishDecisions($success, array $results, array $operations) {
    $messenger = \Drupal::messenger();
    $total = count($results['items']);
    $failed = count($results['failed']);
    $end_time = microtime(TRUE);
    $total_time = ($end_time - $results['starttime']);
    $messenger->addMessage('Processed ' . $total . ' items (' . $failed . ' failed) in ' . $total_time . ' seconds.');
  }

  /**
   * Static callback function for processing motions data.
   *
   * @param mixed $data
   *   Data for operation.
   * @param mixed $context
   *   Context for batch operation.
   */
  public static function processMotionsItem($data, &$context) {
    $context['message'] = 'Importing item number ' . $data['count'];

    if (!isset($context['results']['items'])) {
      $context['results']['items'] = [];
    }
    if (!isset($context['results']['skipped'])) {
      $context['results']['skipped'] = [];
    }
    if (!isset($context['results']['failed'])) {
      $context['results']['failed'] = [];
    }
    if (!isset($context['results']['starttime'])) {
      $context['results']['starttime'] = microtime(TRUE);
    }
    if (!isset($context['results']['update_all'])) {
      $context['results']['update_all'] = $data['update_all'];
    }

    /** @var \Drupal\paatokset_ahjo_proxy\AhjoProxy $ahjo_proxy */
    $ahjo_proxy = \Drupal::service('paatokset_ahjo_proxy');
    /** @var \Drupal\paatokset_ahjo_api\Service\CaseService */
    $case_service = \Drupal::service('paatokset_ahjo_cases');

    if (!empty($data['html'])) {
      $ids = $ahjo_proxy->getCaseDataFromHtml($data['html']);
    }
    else {
      $ids = [];
    }

    // Handle cases where ids are blank.
    if (empty($ids) || !isset($ids['case_id'])) {
      $ids = [
        'case_id' => NULL,
      ];
    }

    // Make sure meeting data exists.
    if (empty($data['meeting_data'])) {
      $context['results']['failed'][] = $data['title'];
      return;
    }

    if (!empty($data['html'])) {
      $motion = $data['html'];
    }
    else {
      $context['results']['failed'][] = $data['title'];
      return;
    }

    $case_id = $ids['case_id'];
    $title = $data['title'];
    $native_id = $data['native_id'];
    $meeting_id = $data['meeting_data']['meeting_id'];
    $meeting_date = $data['meeting_data']['meeting_date'];
    $meeting_number = $data['meeting_data']['meeting_number'];
    $org_id = $data['meeting_data']['org_id'];
    $org_name = $data['meeting_data']['org_name'];

    $node = $case_service->findOrCreateMotion($case_id, $meeting_id, $title, TRUE);
    if (!$node instanceof NodeInterface) {
      $context['results']['failed'][] = $data['title'];
      return;
    }

    // If node is already a decision, skip item.
    if ($node->get('field_is_decision')->value) {
      $context['results']['skipped'][] = $data['title'];
      return;
    }

    // Get record from endpoint, unless we're only using local data.
    $record_content = [];
    if ($data['endpoint']) {
      $record_content = $ahjo_proxy->getData($data['endpoint'], NULL);
    }

    // Local data is formatted a bit differently.
    if (isset($record_content['records'])) {
      $record_content = $record_content['records'][0];
    }

    if (!empty($record_content)) {
      $node->set('field_decision_record', json_encode($record_content));
    }
    // If record content can't or won't be fetched, use PDF from agenda item.
    elseif (!empty($data['pdf'])) {
      $node->set('field_decision_record', json_encode($data['pdf']));
      $record_content = $data['pdf'];
    }
    // If neither can't be used, mark this item as failed.
    else {
      $context['results']['failed'][] = $data['title'];
      return;
    }

    if (isset($record_content['Language'])
      && in_array($record_content['Language'], ['fi', 'sv'])) {
      $langcode = $record_content['Language'];
    }
    else {
      $langcode = 'fi';
    }

    // Get top category name.
    if (isset($ids['classification_code'])) {
      $classification_code = $ids['classification_code'];
      $top_category = $case_service->getTopCategoryFromClassificationCode($classification_code, $langcode);
    }
    else {
      $classification_code = NULL;
      $top_category = NULL;
    }

    // Get data from agenda item endpoint.
    $attachments_json = [];
    $agenda_content = [];
    if ($data['agenda_endpoint']) {
      $agenda_content = $ahjo_proxy->getData($data['agenda_endpoint'], NULL);
    }

    // Local data through proxy is formatted differently.
    if (!empty($agenda_content['agenda_item'])) {
      $agenda_content = $agenda_content['agenda_item'];
    }

    if (!empty($agenda_content)) {
      if (!empty($agenda_content['Attachments'])) {
        foreach ($agenda_content['Attachments'] as $attachment) {
          $attachments_json[] = json_encode($attachment);
        }
      }
      if (!empty($agenda_content['DecisionHistoryHTML'])) {
        $node->set('field_decision_history', [
          'value' => $agenda_content['DecisionHistoryHTML'],
          'format' => 'plain_text',
        ]);
      }
      if (!empty($agenda_content['DecisionHistoryPDF'])) {
        $node->set('field_decision_history_pdf', json_encode($agenda_content['DecisionHistoryPDF']));
      }
    }

    $node->set('field_full_title', $title);
    $node->set('field_is_decision', 0);
    $node->set('field_outdated_document', 1);
    $node->set('field_top_category_name', $top_category);
    $node->set('field_classification_code', $classification_code);
    $node->set('field_decision_native_id', $native_id);
    $node->set('field_diary_number', $case_id);
    $node->set('field_meeting_id', $meeting_id);
    $node->set('field_decision_date', $meeting_date);
    $node->set('field_meeting_date', $meeting_date);
    $node->set('field_meeting_sequence_number', $meeting_number);
    $node->set('field_policymaker_id', $org_id);
    $node->set('field_dm_org_name', $org_name);
    $node->set('field_decision_attachments', $attachments_json);
    $node->set('field_decision_motion', [
      'value' => $motion,
      'format' => 'plain_text',
    ]);

    if (!empty($record_content)) {
      $ahjo_proxy->updateDecisionRecordData($node, $record_content);
    }

    if ($node->save()) {
      $context['results']['items'][] = $data['title'];
    }
    else {
      $context['results']['failed'][] = $data['title'];
    }
  }

  /**
   * Static callback function for finishing motions aggregation batch.
   *
   * @param mixed $success
   *   If batch succeeded or not.
   * @param array $results
   *   Aggregated results.
   * @param array $operations
   *   Operations with errors.
   */
  public static function finishMotions($success, array $results, array $operations) {
    $messenger = \Drupal::messenger();
    $total = count($results['items']);
    $failed = count($results['failed']);
    if (isset($results['skipped'])) {
      $skipped = count($results['skipped']);
    }
    else {
      $skipped = 0;
    }
    $end_time = microtime(TRUE);
    $total_time = ($end_time - $results['starttime']);
    $messenger->addMessage('Processed ' . $total . ' items (' . $failed . ' failed, ' . $skipped . ' skipped) in ' . $total_time . ' seconds.');

    // Save failed array into filesystem even if it's empty so we can wipe it.
    $ahjo_proxy = \Drupal::service('paatokset_ahjo_proxy');
    $ahjo_proxy->fileRepository->writeData(json_encode($results['failed']), 'public://failed_motions.json', FileSystemInterface::EXISTS_REPLACE);
    if (!empty($results['failed'])) {
      $messenger->addMessage('Data for failed items saved into public://failed_motions.json');
    }
  }

  /**
   * Check if decision has an outdated document.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Decision to check.
   *
   * @return bool
   *   Returns TRUE if record is not outdated.
   */
  public function checkDecisionRecord(NodeInterface $node): bool {
    if (!$node->hasField('field_decision_record') || $node->get('field_decision_record')->isEmpty()) {
      return FALSE;
    }

    $allowed_types = [
      'päätös',
      'viranhaltijan päätös',
      'luottamushenkilön päätös',
    ];

    $record_content = json_decode($node->get('field_decision_record')->value, TRUE);
    if (empty($record_content) || !isset($record_content['Type'])) {
      return FALSE;
    }
    elseif (in_array($record_content['Type'], $allowed_types)) {
      return TRUE;
    }
    else {
      return FALSE;
    }

    return FALSE;
  }

  /**
   * Check if meeting has missing motions.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Meeting to check agenda for.
   *
   * @return bool
   *   Returns TRUE if all valid agenda items have motions.
   */
  public function checkMeetingMotions(NodeInterface $node): bool {
    if (!$node->hasField('field_meeting_agenda') || $node->get('field_meeting_agenda')->isEmpty()) {
      return TRUE;
    }

    $meeting_id = $node->get('field_meeting_id')->value;

    /** @var \Drupal\paatokset_ahjo_api\Service\CaseService $caseService */
    $caseService = \Drupal::service('paatokset_ahjo_cases');

    $missing = FALSE;
    foreach ($node->get('field_meeting_agenda') as $field) {
      $item = json_decode($field->value, TRUE);

      // Only check finnish language motions.
      if (!isset($item['PDF']) || $item['PDF']['Language'] !== 'fi') {
        continue;
      }

      if (!isset($item['PDF']['NativeId'])) {
        continue;
      }

      $url = $caseService->getDecisionUrlByTitle($item['AgendaItem'], $meeting_id);
      if (!$url instanceof Url) {
        $missing = TRUE;
        break;
      }
    }

    return !$missing;
  }

  /**
   * Check if meeting has missing decisions.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Meeting to check agenda for.
   *
   * @return array
   *   List of missing decisions.
   */
  public function checkMeetingDecisions(NodeInterface $node): array {
    if (!$node->hasField('field_meeting_agenda') || $node->get('field_meeting_agenda')->isEmpty()) {
      return [];
    }

    $meeting_id = $node->get('field_meeting_id')->value;

    /** @var \Drupal\paatokset_ahjo_api\Service\CaseService $caseService */
    $caseService = \Drupal::service('paatokset_ahjo_cases');
    $missing = [];
    foreach ($node->get('field_meeting_agenda') as $field) {
      $item = json_decode($field->value, TRUE);

      if (!isset($item['PDF']) || !isset($item['PDF']['NativeId'])) {
        continue;
      }

      if (!isset($item['PDF']['Type']) || $item['PDF']['Type'] !== 'päätös') {
        continue;
      }

      if (isset($item['Section'])) {
        $section_clean = (string) intval($item['Section']);
        $url = $caseService->getDecisionUrlByTitle($item['AgendaItem'], $meeting_id, $section_clean);
      }
      else {
        $url = $caseService->getDecisionUrlByTitle($item['AgendaItem'], $meeting_id);
      }

      if (!$url instanceof Url) {
        $missing[] = $item['PDF']['NativeId'];
      }
    }

    return $missing;
  }

  /**
   * Get case diary number and section id from html data.
   *
   * @param string $html
   *   HTML data to parse.
   *
   * @return array|null
   *   Array containing IDs, if found.
   */
  protected function getCaseDataFromHtml(string $html): ?array {
    $dom = new \DOMDocument();
    if (!empty($html)) {
      @$dom->loadHTML($html);
    }
    else {
      return NULL;
    }
    $xpath = new \DOMXPath($dom);
    $divs = $xpath->query("//*[contains(@class, 'DnroTmuoto')]");
    $id_text = '';
    foreach ($divs as $div) {
      $id_text .= $div->nodeValue;
    }

    $bits = explode(' T ', $id_text);

    $diary_label = array_shift($bits);

    if (empty($diary_label)) {
      return NULL;
    }

    $diary_number = str_replace(' ', '-', $diary_label);
    $classification_code = array_pop($bits);

    return [
      'case_id' => $diary_number,
      'case_id_label' => $diary_label,
      'classification_code' => $classification_code,
    ];
  }

  /**
   * Migrate single entity.
   *
   * @param string $endpoint
   *   Endpoint to use.
   * @param string $id
   *   Entity ID.
   *
   * @return int
   *   Migration status.
   *   - Completed: 1
   *   - Incomplete, stopped: 2
   *   - Stopped: 3
   *   - Failed: 4
   *   - Skipped: 5
   *   - Disabled: 6
   */
  public function migrateSingleEntity(string $endpoint, string $id): int {
    // Sometimes callbacks send ID label with spaces.
    $id = str_replace(' ', '-', $id);

    switch ($endpoint) {
      case 'meetings':
        $migration_id = 'ahjo_meetings:single';
        $migration_url = '/ahjo-proxy/meetings/single/';
        break;

      case 'decisions':
        $migration_id = 'ahjo_decisions:single';
        $migration_url = '/ahjo-proxy/decisions/single/';
        break;

      case 'cases':
        $migration_id = 'ahjo_cases:single';
        $migration_url = '/ahjo-proxy/cases/single/';
        break;

      case 'trustees':
        $migration_id = 'ahjo_trustees:single';
        $migration_url = '/ahjo-proxy/trustees/single/';
        break;

      case 'trustees_sv':
        $migration_id = 'ahjo_trustees:single_sv';
        $migration_url = '/ahjo-proxy/trustees/single/';
        break;

      case 'organization':
        $migration_id = 'ahjo_decisionmakers:single';
        $migration_url = '/ahjo-proxy/organization/single/';
        break;

      case 'organization_sv':
        $migration_id = 'ahjo_decisionmakers:single_sv';
        $migration_url = '/ahjo-proxy/organization/single/';
        break;

      default:
        $migration_id = NULL;
        $migration_url = NULL;
        break;
    }

    // Invalid ID or URL, return "Skipped" because dependencies couldn't be met.
    if (!$migration_id || !$migration_url) {
      return 5;
    }

    // Get either local proxy URL or OpenShift reverse proxy address.
    if (getenv('AHJO_PROXY_BASE_URL')) {
      $base_url = getenv('AHJO_PROXY_BASE_URL');
    }
    elseif (getenv('DRUPAL_REVERSE_PROXY_ADDRESS')) {
      $base_url = 'https://' . getenv('DRUPAL_REVERSE_PROXY_ADDRESS');
    }
    else {
      $base_url = '';
    }

    $endpoint_url = $base_url . $migration_url . $id;

    if (strpos($migration_id, '_sv') !== FALSE) {
      $endpoint_url .= '?apireqlang=sv';
    }

    // Attempt to fetch content first, because
    // migration doesn't complain about empty results.
    $data = $this->getContent($endpoint_url);
    if (empty(reset($data))) {
      return 0;
    }

    $migration = $this->migrationManager->createInstance($migration_id, [
      'source' => [
        'urls' => [
          $endpoint_url,
        ],
      ],
    ]);

    // Migration couldn't be loaded, so return with "disabled" status.
    if (!$migration) {
      return 6;
    }

    // Always update even if entity exists.
    $migration->getIdMap()->prepareUpdate();

    // Execute migration.
    $executable = new MigrateExecutable($migration, new MigrateMessage());
    $status = $executable->import();
    return $status;
  }

  /**
   * Mark meetings with specific meeting ID to be reprocessed.
   *
   * @param string $meeting_id
   *   Meeting ID.
   */
  public function markMeetingMotionsAsUnprocessed(string $meeting_id): void {
    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', 'meeting')
      ->condition('status', 1)
      ->condition('field_meeting_agenda_published', 1)
      ->condition('field_meeting_minutes_published', 0)
      ->condition('field_meeting_agenda', '', '<>')
      ->condition('field_agenda_items_processed', 1)
      ->condition('field_meeting_id', $meeting_id)
      ->latestRevision();

    $ids = $query->execute();
    if (empty($ids)) {
      return;
    }

    $nodes = Node::loadMultiple($ids);
    foreach ($nodes as $node) {
      if ($node instanceof NodeInterface) {
        $node->set('field_agenda_items_processed', 0);
        $node->save();
      }
    }
  }

  /**
   * Get blacklisted entity IDs from config.
   *
   * @return array
   *   Empty array or valus from config.
   */
  public function getBlacklistedIds(): array {
    $blacklist_config = $this->config->get('paatokset_ahjo_proxy.blacklist');
    $ids_text = $blacklist_config->get('ids');
    if (empty($ids_text)) {
      return [];
    }
    $ids = explode(PHP_EOL, $ids_text);
    foreach ($ids as $key => $value) {
      $ids[$key] = trim($value);
    }
    return $ids;
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
      // Check if URL is internal to Drupal or a proxy URL.
      if ($this->isLocalOrProxyUrl($url)) {
        $headers = $this->getLocalAuthHeaders();
      }
      else {
        $headers = $this->getAuthHeaders();
      }

      $response = $this->httpClient->request('GET', $url,
      [
        'http_errors' => FALSE,
        'headers' => $headers,
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
   * @param string $nativeId
   *   Native ID for the file. Should already be urlencoded.
   *
   * @return GuzzleHttp\Psr7\Response
   *   Response from the API.
   */
  public function getFile(string $nativeId): ?Response {
    $url = $this->getApiFileUrl() . $nativeId;

    try {
      $response = $this->httpClient->request('GET', $url,
      [
        'http_errors' => FALSE,
        'headers' => $this->getAuthHeaders(),
      ]);

      if ($response->getStatusCode() !== 200) {
        return NULL;
      }

      return $response;
    }
    catch (\Exception $e) {
    }
    return NULL;
  }

  /**
   * Send a HEAD request and return status code.
   *
   * @param string $url
   *   URL to send request for.
   *
   * @return string
   *   HTTP status code.
   */
  public function headStatusRequest(string $url): int {
    $response = $this->httpClient->request('HEAD', $url,
    [
      'http_errors' => FALSE,
    ]);
    return $response->getStatusCode();
  }

  /**
   * Get authentication headers for HTTP requests.
   *
   * @return array
   *   Headers for the request or empty array if config/token is missing.
   */
  private function getAuthHeaders(): array {
    // We might want to skip auth headers locally.
    if (getenv('SKIP_AUTH_HEADERS')) {
      // Unless we want to use an API key when querying the proxy.
      if (!empty(getenv('LOCAL_PROXY_API_KEY'))) {
        return [
          'api-key' => getenv('LOCAL_PROXY_API_KEY'),
        ];
      }
      return [];
    }

    // Check if access token is still valid (not expired).
    if ($this->ahjoOpenId->checkAuthToken()) {
      $access_token = $this->ahjoOpenId->getAuthToken();
    }
    else {
      // Refresh and return new access token.
      $access_token = $this->ahjoOpenId->refreshAuthToken();
    }

    if (!$access_token) {
      return [];
    }

    $headers = [
      'Authorization' => 'Bearer ' . $access_token,
    ];

    $cookies = $this->ahjoOpenId->getCookies();
    if ($cookies) {
      $headers['Cookie'] = $cookies;
    }

    return $headers;
  }

  /**
   * Get authentication headers for local or proxy URL HTTP requests.
   *
   * @return array
   *   Headers for the request or empty array if api key is missing.
   */
  private function getLocalAuthHeaders(): array {
    if (!empty(getenv('LOCAL_PROXY_API_KEY'))) {
      return [
        'api-key' => getenv('LOCAL_PROXY_API_KEY'),
      ];
    }

    return [];
  }

  /**
   * Checks if URL is local or a proxy URL.
   *
   * @param string $url
   *   URL to check.
   *
   * @return bool
   *   Returns TRUE if URL is internal to Drupal.
   */
  private function isLocalOrProxyUrl(string $url): bool {
    if (!empty(getenv('DRUPAL_REVERSE_PROXY_ADDRESS'))) {
      $proxy_base = getenv('DRUPAL_REVERSE_PROXY_ADDRESS');
    }
    elseif (!empty(getenv('AHJO_PROXY_BASE_URL'))) {
      $proxy_base = getenv('AHJO_PROXY_BASE_URL');
    }
    else {
      return FALSE;
    }

    if (strpos($url, $proxy_base) !== FALSE) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Check if proxy / open ID configuration is set and tokens are valid.
   *
   * @return bool
   *   If proxy is operational.
   */
  public function isOperational(): bool {
    // If we're using a proxy instead of the Ahjo API, we can skip this check.
    if (getenv('SKIP_AUTH_HEADERS')) {
      return TRUE;
    }

    // Check if access token is still valid (not expired).
    if ($this->ahjoOpenId->checkAuthToken()) {
      $access_token = $this->ahjoOpenId->getAuthToken();
    }
    else {
      // Refresh and return new access token.
      $access_token = $this->ahjoOpenId->refreshAuthToken();
    }

    if (!$access_token) {
      return FALSE;
    }
    return TRUE;
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
    $id = preg_replace('/[^a-zA-Z0-9_]+/s', '_', $id);
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
      if (is_array($data->data)) {
        return $data->data;
      }
      else {
        return json_decode($data->data, TRUE);
      }
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

  /**
   * Invalidate ahjo proxy cache, mainly for callbacks.
   *
   * @param string $endpoint
   *   Which endpoint to use.
   * @param string $id
   *   ID for entity.
   */
  public function invalideCacheForProxy(string $endpoint, string $id): void {
    // Proxy URLs used for migrations have an empty query string.
    $url = $this->getApiBaseUrl() . $endpoint . '/' . strtoupper($id) . '?';
    $delete_key = $this->getCacheKey($url);
    $this->dataCache->invalidate($delete_key);
    $this->logger->info('Invalidated cache for URL: @url with @key', [
      '@url' => $url,
      '@key' => $delete_key,
    ]);
  }

  /**
   * Get API Base URL.
   *
   * @return string
   *   API Base URL from config or default (prod).
   */
  public function getApiBaseUrl(): string {
    $config = $this->config->get('paatokset_ahjo_proxy.settings');

    if ($url = $config->get('api_base_url')) {
      return $url;
    }

    return 'https://ahjo.hel.fi:9802/ahjorest/v1/';
  }

  /**
   * Get API File URL.
   *
   * @return string
   *   API File URL from config or default (prod).
   */
  public function getApiFileUrl(): string {
    $config = $this->config->get('paatokset_ahjo_proxy.settings');

    if ($url = $config->get('api_file_url')) {
      return $url;
    }

    return 'https://ahjo.hel.fi:9802/ahjorest/v1/content/';
  }

}
