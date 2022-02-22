<?php

declare(strict_types = 1);

namespace Drupal\paatokset_ahjo_proxy;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\file\FileInterface;
use Drupal\node\NodeInterface;
use Drupal\paatokset_ahjo_openid\AhjoOpenId;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\Psr7\Response;
use Drupal\node\Entity\Node;
use Drupal\migrate\Plugin\MigrationPluginManager;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\MigrateExecutable;

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
   * @param \Drupal\migrate\Plugin\MigrationPluginManager $migration_manager
   *   Migration manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory service.
   * @param \Drupal\file\FileRepositoryInterface $file_repository
   *   File repository.
   * @param \Drupal\paatokset_ahjo_openid\AhjoOpenId $ahjo_open_id
   *   Ahjo Open ID service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(ClientInterface $http_client, CacheBackendInterface $data_cache, EntityTypeManagerInterface $entity_type_manager, MigrationPluginManager $migration_manager, LoggerChannelFactoryInterface $logger_factory, FileRepositoryInterface $file_repository, AhjoOpenId $ahjo_open_id) {
    $this->httpClient = $http_client;
    $this->dataCache = $data_cache;
    $this->ahjoOpenId = $ahjo_open_id;
    $this->entityTypeManager = $entity_type_manager;
    $this->migrationManager = $migration_manager;
    $this->fileRepository = $file_repository;
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
      $container->get('plugin.manager.migration'),
      $container->get('logger.factory'),
      $container->get('file.repository'),
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
    $cases_url = self::API_BASE_URL . 'cases/' . $id . '?' . urldecode($query_string);
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
    $decisions_url = self::API_BASE_URL . 'decisions/' . $id . '?' . urldecode($query_string);
    $decision = $this->getContent($decisions_url, $bypass_cache);

    // Single decisions are already inside an array.
    return ['decisions' => $decision];
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
    $agent_url = self::API_BASE_URL . 'agents/positionoftrust/' . $id . '?' . urldecode($query_string);
    $agent = $this->getContent($agent_url, $bypass_cache);
    return ['trustees' => [$agent]];
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

      case 'trustees_council':
        $filename = 'trustees_council.json';
        break;

      case 'decisionmakers':
        $filename = 'decisionmakers.json';
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

    /** @var \Drupal\paatokset_ahjo_proxy\AhjoProxy $ahjo_proxy */
    $ahjo_proxy = \Drupal::service('paatokset_ahjo_proxy');
    $full_data = $ahjo_proxy->getData($data['endpoint'], NULL);

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

    if (!empty($record_content)) {
      $node->set('field_decision_record', json_encode($record_content));
    }
    else {
      $fetch_record_from_case = TRUE;
    }

    if (isset($record_content['Issued'])) {
      $date = new \DateTime($record_content['Issued'], new \DateTimeZone('Europe/Helsinki'));
      $date->setTimezone(new \DateTimeZone('UTC'));
      $node->set('field_decision_date', $date->format('Y-m-d\TH:i:s'));
    }

    if (isset($record_content['Created'])) {
      $created_date = new \DateTime($record_content['Created'], new \DateTimeZone('Europe/Helsinki'));
      $created_date->setTimezone(new \DateTimeZone('UTC'));
      $node->set('field_meeting_date', $created_date->format('Y-m-d\TH:i:s'));
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
      return;
    }

    $nid = array_shift($nids);
    $case = Node::load($nid);

    if (!$case instanceof NodeInterface) {
      return;
    }

    $node->set('field_decision_case_title', $case->field_full_title->value);

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

    // If decision content can't be set, consider this failed.
    if ($node->get('field_decision_content_parsed')->isEmpty()) {
      $messenger->addMessage('Content parsing failed for decision with nid: ' . $data['nid']);
      $context['results']['failed'][] = $node->id();
    }
    else {
      $context['results']['items'][] = $node->id();
      $node->save();
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

    $ids = $ahjo_proxy->getCaseDataFromHtml($data['html']);

    // Skip items without diary numbers.
    if (empty($ids) || !isset($ids['case_id'])) {
      $context['results']['skipped'][] = $data['title'];
      return;
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

    // Get top category name.
    if (isset($ids['classification_code'])) {
      $classification_code = $ids['classification_code'];
      $top_category = $case_service->getTopCategoryFromClassificationCode($classification_code);
    }
    else {
      $classification_code = NULL;
      $top_category = NULL;
    }

    $node = $case_service->findOrCreateMotion($case_id, $meeting_id, $title);
    if (!$node instanceof NodeInterface) {
      $context['results']['failed'][] = $data['title'];
      return;
    }

    // If node is already a decision, skip item.
    if ($node->get('field_is_decision')->value) {
      $context['results']['skipped'][] = $data['title'];
      return;
    }

    // If node is not new and we're not updating everything, skip item.
    if (!$data['update_all'] && !$node->isNew()) {
      $context['results']['skipped'][] = $data['title'];
      return;
    }

    // Get record from endpoint, unless we're only using local data.
    $record_content = [];
    if ($data['endpoint']) {
      $record_content = $ahjo_proxy->getData($data['endpoint'], NULL);
    }

    if (!empty($record_content)) {
      $node->set('field_decision_record', json_encode($record_content));
    }
    // If record content can't or won't be fetched, use PDF from agenda item.
    elseif (!empty($data['pdf'])) {
      $node->set('field_decision_record', json_encode($data['pdf']));
    }
    // If neither can't be used, mark this item as failed.
    else {
      $context['results']['failed'][] = $data['title'];
      return;
    }

    $node->set('field_full_title', $title);
    $node->set('field_is_decision', 0);
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
    $node->set('field_decision_motion', [
      'value' => $motion,
      'format' => 'plain_text',
    ]);

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
    file_save_data(json_encode($results['failed']), 'public://failed_motions.json', FileSystemInterface::EXISTS_REPLACE);
    if (!empty($results['failed'])) {
      $messenger->addMessage('Data for failed items saved into public://failed_motions.json');
    }
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

      default:
        $migration_id = NULL;
        $migration_url = NULL;
        break;
    }

    // Invalid ID or URL, return "Skipped" because dependencies couldn't be met.
    if (!$migration_id || !$migration_url) {
      return 5;
    }

    // Attempt to fetch content first, because
    // migration doesn't complain about empty results.
    if ($endpoint === 'trustees') {
      $endpoint_url = self::API_BASE_URL . 'agents/positionoftrust/' . $id;
    }
    else {
      $endpoint_url = self::API_BASE_URL . $endpoint . '/' . $id;
    }

    $data = $this->getContent($endpoint_url);
    if (empty($data)) {
      return 0;
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

    $migration = $this->migrationManager->createInstance($migration_id, [
      'source' => [
        'urls' => [
          $base_url . $migration_url . $id,
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
   * @param string $nativeId
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
        return NULL;
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
   * Check if proxy / open ID configuration is set and tokens are valid.
   *
   * @return bool
   *   If proxy is operational.
   */
  public function isOperational(): bool {
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
