<?php

declare(strict_types = 1);

namespace Drupal\paatokset_ahjo_proxy\Commands;

use Drush\Commands\DrushCommands;
use Drupal\file\FileRepositoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\paatokset_ahjo_proxy\AhjoProxy;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\node\NodeStorageInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\path_alias\Entity\PathAlias;
use Symfony\Component\Console\Helper\Table;

/**
 * Ahjo Aggregator drush commands.
 *
 * @package Drupal\paatokset_ahjo_proxy\Commands
 */
class AhjoAggregatorCommands extends DrushCommands {

  /**
   * Ahjo proxy service.
   *
   * @var \Drupal\paatokset_ahjo_proxy\AhjoProxy
   */
  protected $ahjoProxy;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * File repository service.
   *
   * @var \Drupal\file\FileRepositoryInterface
   */
  protected $fileRepository;

  /**
   * Node storage service.
   *
   * @var \Drupal\node\NodeStorageInterface
   */
  protected NodeStorageInterface $nodeStorage;

  /**
   * Constructor for Ahjo Aggregator Commands.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   Logger service.
   * @param \Drupal\paatokset_ahjo_proxy\AhjoProxy $ahjo_proxy
   *   Ahjo Proxy service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\file\FileRepositoryInterface $file_repository
   *   File repository.
   */
  public function __construct(LoggerChannelFactoryInterface $logger_factory, AhjoProxy $ahjo_proxy, EntityTypeManagerInterface $entity_type_manager, FileRepositoryInterface $file_repository) {
    $this->ahjoProxy = $ahjo_proxy;
    $this->entityTypeManager = $entity_type_manager;
    $this->nodeStorage = $this->entityTypeManager->getStorage('node');
    $this->logger = $logger_factory->get('paatokset_ahjo_proxy');
    $this->fileRepository = $file_repository;
  }

  /**
   * Aggregates data from Ahjo API.
   *
   * @param string $endpoint
   *   Endpoint to aggregate data from.
   * @param array $options
   *   Additional options for the command.
   *
   * @command ahjo-proxy:aggregate
   *
   * @option dataset
   *   Which dataset to get (latest, all, etc)
   * @option start
   *   Custom timestamp for fetching data.
   * @option end
   *   Custom timestamp for fetching data.
   * @option retry
   *   Filename to retry from.
   * @option append
   *   File to append to. Useful when retrying.
   * @option filename
   *   Filename to use instead of default. Can be used to split/batch results.
   *
   * @usage ahjo-proxy:aggregate meetings --dataset=latest
   *   Stores latest meetings into meetings_latest.json
   * @usage ahjo-proxy:aggregate meetings --dataset=all --retry=failed_meetings_all.json --append=meetings_all.json
   *   Retries failed aggregation based on stored file.
   *
   * @aliases ap:agg
   */
  public function aggregate(string $endpoint, array $options = [
    'dataset' => NULL,
    'start' => NULL,
    'end' => NULL,
    'cancelledonly' => NULL,
    'retry' => NULL,
    'filename' => NULL,
    'append' => NULL,
  ]): void {

    $allowed_datasets = [
      'all',
      'latest',
      'cancelled',
    ];

    if (in_array($options['dataset'], $allowed_datasets)) {
      $dataset = $options['dataset'];
    }
    else {
      $dataset = 'latest';
    }

    $options = $this->setDefaultOptions($endpoint, $dataset, $options);

    $data = [];
    $list_key = $this->getListKey($endpoint);

    $this->logger->info('Fetching from ' . $endpoint . ' with query string: ' . $options['query_string']);
    if (!empty($options['retry'])) {
      $this->logger->info('Resuming from file: ' . $options['retry']);
      $data = $this->ahjoProxy->getStatic($options['retry']);
    }
    else {
      $data = $this->ahjoProxy->getData($endpoint, $options['query_string']);
    }

    if (!empty($options['append'])) {
      $append_results = $this->ahjoProxy->getStatic($options['append']);
      $this->logger->info('Combining with file: ' . $options['append']);
    }

    if (!empty($options['filename'])) {
      $filename = $options['filename'];
      $this->logger->info('Using filename: ' . $filename);
    }
    else {
      $filename = NULL;
    }

    if (empty($data[$list_key])) {
      $this->logger->info('Empty result.');
      return;
    }

    $this->logger->info('Processing ' . count($data[$list_key]) . ' results.');

    // Get property key for ID value based on endpoint.
    switch ($endpoint) {
      case 'cases':
        $id_key = 'CaseID';
        break;

      case 'meetings':
        $id_key = 'MeetingID';
        break;

      default:
        $id_key = 'NativeId';
        break;
    }

    $operations = [];
    $count = 0;
    foreach ($data[$list_key] as $item) {
      $count++;
      $data = [
        'item' => $item,
        'item_id' => $item[$id_key],
        'list_key' => $list_key,
        'endpoint' => $endpoint,
        'dataset' => $dataset,
        'count' => $count,
        'append' => NULL,
        'filename' => $filename,
      ];

      if ($count === 1 && !empty($append_results[$list_key])) {
        $data['append'] = $append_results[$list_key];
      }

      $operations[] = [
        '\Drupal\paatokset_ahjo_proxy\AhjoProxy::processBatchItem',
        [$data],
      ];
    }

    if (empty($operations)) {
      $this->logger->info('Nothing to import.');
      return;
    }

    batch_set([
      'title' => 'Aggregating: ' . $endpoint . ' with dataset:' . $dataset,
      'operations' => $operations,
      'finished' => '\Drupal\paatokset_ahjo_proxy\AhjoProxy::finishBatch',
    ]);

    drush_backend_batch_process();
  }

  /**
   * Gets and stores a single request from Ahjo API.
   *
   * @param string $endpoint
   *   Endpoint to get data from.
   * @param array $options
   *   Additional options for the command.
   *
   * @command ahjo-proxy:get
   *
   * @option changedsince
   *   Custom timestamp for fetching data.
   * @option changedbefore
   *   Custom timestamp for fetching data.
   * @option filename
   *   Filename to use instead of default. Can be used to split/batch results.
   * @option langcode
   *   Langcode to get data for.
   *
   * @usage ahjo-proxy:get decisionmakers
   *   Stores all decisionmakers into decisionmakers.json
   * @usage ahjo-proxy:get decisionmakers --start=021100VH1 --filename=decisionmakers_021100VH1.json
   *   Stores decisionmakers under the 021100VH1 organisation.
   *
   * @aliases ap:get
   */
  public function get(string $endpoint, array $options = [
    'dataset' => NULL,
    'start' => NULL,
    'end' => NULL,
    'changedsince' => NULL,
    'changedbefore' => NULL,
    'handledsince' => NULL,
    'handledbefore' => NULL,
    'langcode' => NULL,
    'filename' => NULL,
  ]): void {
    $data = [];
    $list_key = $this->getListKey($endpoint);

    if (!empty($options['dataset'])) {
      $dataset = $options['dataset'];
    }
    else {
      $dataset = 'all';
    }

    if ($dataset === 'latest') {
      $week_ago = strtotime("-1 week");
      $timestamp = date('Y-m-d\TH:i:s\Z', $week_ago);
    }

    $query_string = '';
    if (!empty($options['start'])) {
      $query_string .= 'start=' . $options['start'];
    }
    if (!empty($options['end'])) {
      $query_string .= '&end=' . $options['end'];
    }
    if (!empty($options['changedsince'])) {
      $query_string .= '&changedsince=' . $options['changedsince'];
    }
    elseif ($endpoint === 'decisionmakers' && $dataset === 'latest') {
      $query_string .= '&changedsince=' . $timestamp;
    }

    if (!empty($options['handledsince'])) {
      $query_string .= '&handledsince=' . $options['handledsince'];
    }

    if (!empty($options['changedbefore'])) {
      $query_string .= '&changedbefore=' . $options['changedbefore'];
    }
    if (!empty($options['handledbefore'])) {
      $query_string .= '&handledbefore=' . $options['handledbefore'];
    }
    if (!empty($options['langcode'])) {
      $query_string .= '&apireqlang=' . $options['langcode'];
    }

    $this->logger->info('Fetching from ' . $endpoint . ' with query string: ' . $query_string);

    $data = $this->ahjoProxy->getData($endpoint, $query_string);

    if (!empty($options['filename'])) {
      $filename = $options['filename'];
      $this->logger->info('Using filename: ' . $filename);
    }
    else {
      $filename = $endpoint . '.json';
    }

    if (empty($data[$list_key])) {
      $this->logger->info('Empty result.');
      return;
    }

    $this->logger->info('Received ' . count($data[$list_key]) . ' results.');
    $this->fileRepository->writeData(json_encode($data), 'public://' . $filename, FileSystemInterface::EXISTS_REPLACE);
    $this->logger->info('Stores data into public://' . $filename);
  }

  /**
   * Aggregates position of trust data from Ahjo API.
   *
   * @command ahjo-proxy:get-positionsoftrust
   *
   * @usage ahjo-proxy:get-positionsoftrust
   *   Stores all positions of trust into a file.
   *
   * @aliases ap:pt
   */
  public function positionsoftrust(): void {
    $this->logger->info('Fetching decision making organizations...');
    $org_data = $this->ahjoProxy->getData('organization/decisionmakingorganizations', NULL);
    if (empty($org_data)) {
      $this->logger->info('Empty result.');
      $org_data = [
        'organizations' => [],
      ];
    }
    else {
      $this->logger->info('Processing ' . count($org_data['organizations']) . ' results.');
    }

    $this->logger->info('Fetching council groups...');
    $group_data = $this->ahjoProxy->getData('councilgroups', NULL);
    if (empty($group_data)) {
      $this->logger->info('Empty result.');
      $group_data = [
        'agentPublicList' => [],
      ];
    }
    else {
      $this->logger->info('Processing ' . count($group_data['agentPublicList']) . ' results.');
    }

    $operations = [];
    $count = 0;
    foreach ($org_data['organizations'] as $org) {
      $count++;
      $data = [
        'endpoint' => 'agents/positionoftrust',
        'count' => $count,
        'query_string' => 'org=' . $org['ID'],
      ];

      $operations[] = [
        '\Drupal\paatokset_ahjo_proxy\AhjoProxy::processGroupItem',
        [$data],
      ];
    }

    foreach ($group_data['agentPublicList'] as $group) {
      $count++;
      $data = [
        'endpoint' => 'agents/positionoftrust',
        'count' => $count,
        'filename' => 'positionsoftrust.json',
        'query_string' => 'org=' . $group['ID'],
      ];

      $operations[] = [
        '\Drupal\paatokset_ahjo_proxy\AhjoProxy::processGroupItem',
        [$data],
      ];
    }

    if (empty($operations)) {
      $this->logger->info('Nothing to import.');
      return;
    }

    batch_set([
      'title' => 'Aggregating council groups and organizations.',
      'operations' => $operations,
      'finished' => '\Drupal\paatokset_ahjo_proxy\AhjoProxy::finishGroups',
    ]);

    drush_backend_batch_process();
  }

  /**
   * Aggregates city council position of trust data from Ahjo API.
   *
   * @command ahjo-proxy:get-council-positionsoftrust
   *
   * @usage ahjo-proxy:get-council-positionsoftrust
   *   Stores all positions of trust into a file.
   *
   * @aliases ap:cpt
   */
  public function councilPositionsOfTrust(): void {
    $council_groups = [
      '02900',
      '00400',
    ];

    $count = 0;
    foreach ($council_groups as $id) {
      $count++;
      $data = [
        'endpoint' => 'agents/positionoftrust',
        'count' => $count,
        'filename' => 'positionsoftrust_council.json',
        'query_string' => 'org=' . $id,
      ];

      $operations[] = [
        '\Drupal\paatokset_ahjo_proxy\AhjoProxy::processGroupItem',
        [$data],
      ];
    }

    if (empty($operations)) {
      $this->logger->info('Nothing to import.');
      return;
    }

    batch_set([
      'title' => 'Aggregating council groups and organizations.',
      'operations' => $operations,
      'finished' => '\Drupal\paatokset_ahjo_proxy\AhjoProxy::finishGroups',
    ]);

    drush_backend_batch_process();
  }

  /**
   * Aggregates trustees from Ahjo API. Requires positions to be aggregated.
   *
   * @param string $filename
   *   Filename to get initial data from.
   *
   * @command ahjo-proxy:get-trustees
   *
   * @usage ahjo-proxy:get-trustees
   *   Stores all positions of trust into a file.
   *
   * @aliases ap:trust
   */
  public function trustees(string $filename = 'positionsoftrust.json'): void {
    $this->logger->info('Fetching trustees organizations...');
    $data = $this->ahjoProxy->getStatic($filename);
    $operations = [];
    $count = 0;
    $ids = [];
    $duplicates = 0;
    foreach ($data as $group) {
      foreach ($group['agentPublicList'] as $item) {
        if (isset($ids[$item['ID']])) {
          $duplicates++;
        }
        else {
          $ids[$item['ID']] = $item['ID'];
          $count++;
          $data = [
            'endpoint' => 'agents/positionoftrust/' . $item['ID'],
            'count' => $count,
          ];
          if ($filename === 'positionsoftrust_council.json') {
            $data['filename'] = 'trustees_council.json';
          }
          $operations[] = [
            '\Drupal\paatokset_ahjo_proxy\AhjoProxy::processTrusteeItem',
            [$data],
          ];
        }
      }
    }

    if (empty($operations)) {
      $this->logger->info('Nothing to import.');
      return;
    }

    $this->logger->info('Prosessing individual positions of trust, total of ' . $count . ' unique items and ' . $duplicates . ' duplicates.');

    batch_set([
      'title' => 'Aggregating positions of trust.',
      'operations' => $operations,
      'finished' => '\Drupal\paatokset_ahjo_proxy\AhjoProxy::finishTrustees',
    ]);

    drush_backend_batch_process();
  }

  /**
   * Updates migrated decision nodes with record, case and meeting info.
   *
   * @param array $options
   *   Additional options for the command.
   *
   * @command ahjo-proxy:update-decisions
   *
   * @option update
   *   Update all decisions, not just ones with missing records.
   * @option logic
   *   Logic on how to check which decisions to update. Irrelevant if
   *   update flag is set.
   * @option localdata
   *   Use only local and placeholder data, doesn't require VPN connection.
   * @option limit
   *   Limit processing to certain amount of nodes.
   *
   * @usage ahjo-proxy:update-decisions
   *   Fetches data for decisions where the record field is null.
   * @usage ahjo-proxy:update-decisions --update
   *   Fetches and updates data for all decisions.
   *
   * @aliases ap:ud
   */
  public function updateDecisions(array $options = [
    'update' => NULL,
    'logic' => 'record',
    'localdata' => NULL,
    'limit' => NULL,
  ]): void {

    if (!empty($options['update'])) {
      $update_all = TRUE;
    }
    else {
      $update_all = FALSE;
    }

    if (!empty($options['logic'])) {
      $logic = $options['logic'];
    }
    else {
      $logic = 'record';
    }

    if (!empty($options['localdata'])) {
      $use_local_data = TRUE;
    }
    else {
      $use_local_data = FALSE;
    }

    if (!empty($options['limit'])) {
      $limit = (int) $options['limit'];
    }
    else {
      $limit = 0;
    }

    if ($update_all) {
      $this->logger->info('Updating all nodes...');
    }
    else {
      $this->logger->info('Only updating nodes based on missing ' . $logic . ' data.');
    }

    if ($use_local_data) {
      $this->logger->info('Using local data...');
    }
    else {
      $this->logger->info('Fetching data from API...');
    }

    $this->logger->info('Limiting nodes to: ' . $limit);

    $query = $this->nodeStorage->getQuery()
      ->condition('type', 'decision')
      ->condition('status', 1)
      ->latestRevision();

    if (!$update_all) {
      if ($logic === 'record') {
        $query->notExists('field_decision_record');
      }
      elseif ($logic === 'language') {
        $query->notExists('field_record_language_checked');
        $query->condition('field_record_language_checked', 0);
      }
      elseif ($logic === 'outdated') {
        $query->condition('field_is_decision', 1);
        $query->condition('field_outdated_document', 1);
      }
      elseif ($logic === 'case') {
        $query->notExists('field_decision_case_title');
        $query->condition('field_diary_number', '', '<>');
      }
      elseif ($logic === 'meeting') {
        $query->notExists('field_meeting_date');
        $query->condition('field_meeting_id', '', '<>');
      }
      elseif ($logic === 'creation') {
        $query->notExists('field_meeting_date');
        $query->notExists('field_meeting_id');
      }
      elseif ($logic === 'uniqueid') {
        $query->notExists('field_unique_id');
      }
    }

    if ($limit) {
      $query->range('0', $limit);
    }

    $ids = $query->execute();
    $this->logger->info('Total nodes: ' . count($ids));

    $nodes = Node::loadMultiple($ids);
    $operations = [];
    $count = 0;
    foreach ($nodes as $node) {
      if (!$node->hasField('field_decision_native_id') || $node->get('field_decision_native_id')->isEmpty()) {
        continue;
      }

      $meeting_id = NULL;
      if ($node->hasField('field_meeting_id')) {
        $meeting_id = $node->field_meeting_id->value;
      }

      $case_id = NULL;
      if ($node->hasField('field_diary_number')) {
        $case_id = $node->field_diary_number->value;
      }

      $endpoint = NULL;
      if (!$use_local_data) {
        $endpoint = 'records/' . $node->field_decision_native_id->value;
      }

      $count++;
      $data = [
        'nid' => $node->id(),
        'native_id' => $node->field_decision_native_id->value,
        'count' => $count,
        'case_id' => $case_id,
        'meeting_id' => $meeting_id,
        'endpoint' => $endpoint,
      ];

      $operations[] = [
        '\Drupal\paatokset_ahjo_proxy\AhjoProxy::processDecisionItem',
        [$data],
      ];
    }

    if (empty($operations)) {
      $this->logger->info('Nothing to import.');
      return;
    }

    batch_set([
      'title' => 'Aggregating data for decisions.',
      'operations' => $operations,
      'finished' => '\Drupal\paatokset_ahjo_proxy\AhjoProxy::finishDecisions',
    ]);

    drush_backend_batch_process();
  }

  /**
   * Updates migrated case nodes with missing info.
   *
   * @param array $options
   *   Additional options for the command.
   *
   * @command ahjo-proxy:update-cases
   *
   * @option logic
   *   Logic on how to check which cases to update.
   * @option limit
   *   Limit processing to certain amount of nodes.
   *
   * @usage ahjo-proxy:update-cases
   *   Fetches data for decisions where the publicity class field is null.
   *
   * @aliases ap:uc
   */
  public function updateCases(array $options = [
    'logic' => 'publicity',
    'localdata' => NULL,
    'limit' => NULL,
  ]): void {

    if (!empty($options['update'])) {
      $update_all = TRUE;
    }
    else {
      $update_all = FALSE;
    }

    if (!empty($options['logic'])) {
      $logic = $options['logic'];
    }
    else {
      $logic = 'publicity';
    }

    if (!empty($options['limit'])) {
      $limit = (int) $options['limit'];
    }
    else {
      $limit = 0;
    }

    $this->logger->info('Updating nodes based on missing ' . $logic . ' data.');
    $this->logger->info('Fetching data from API...');
    $this->logger->info('Limiting nodes to: ' . $limit);

    $query = $this->nodeStorage->getQuery()
      ->condition('type', 'case')
      ->condition('status', 1)
      ->latestRevision();

    if ($logic === 'publicity') {
      $or = $query->orConditionGroup();
      $or->notExists('field_publicity_class');
      $or->condition('field_publicity_class', '');
      $query->condition($or);
    }
    elseif ($logic === 'securityreasons') {
      $or = $query->orConditionGroup();
      $or->notExists('field_security_reasons');
      $or->condition('field_security_reasons', '');
      $query->condition($or);
    }

    if ($limit) {
      $query->range('0', $limit);
    }

    $ids = $query->execute();
    $this->logger->info('Total nodes: ' . count($ids));

    $nodes = Node::loadMultiple($ids);
    $operations = [];
    $count = 0;
    foreach ($nodes as $node) {
      if (!$node->hasField('field_diary_number') || $node->get('field_diary_number')->isEmpty()) {
        continue;
      }

      $case_id = $node->field_diary_number->value;
      // Local adjustments for fetching cases through proxy.
      if (!empty(getenv('AHJO_PROXY_BASE_URL'))) {
        $endpoint = 'cases/single/' . $case_id;
      }
      else {
        $endpoint = 'cases/' . $case_id;
      }

      $count++;
      $data = [
        'nid' => $node->id(),
        'count' => $count,
        'case_id' => $case_id,
        'endpoint' => $endpoint,
      ];

      $operations[] = [
        '\Drupal\paatokset_ahjo_proxy\AhjoProxy::processCaseItem',
        [$data],
      ];
    }

    if (empty($operations)) {
      $this->logger->info('Nothing to import.');
      return;
    }

    batch_set([
      'title' => 'Aggregating data for cases.',
      'operations' => $operations,
      'finished' => '\Drupal\paatokset_ahjo_proxy\AhjoProxy::finishDecisions',
    ]);

    drush_backend_batch_process();
  }

  /**
   * Updates decision node attachments.
   *
   * @param array $options
   *   Additional options for the command.
   *
   * @command ahjo-proxy:update-decision-attachments
   *
   * @option limit
   *   Limit processing to certain amount of nodes.
   *
   * @aliases ap:uda
   */
  public function updateDecisionAttachments(array $options = [
    'limit' => NULL,
  ]): void {
    if (!empty($options['limit'])) {
      $limit = (int) $options['limit'];
    }
    else {
      $limit = 0;
    }

    $this->logger->info('Fetching data from API...');
    $this->logger->info('Limiting nodes to: ' . $limit);

    $query = $this->nodeStorage->getQuery()
      ->condition('type', 'decision')
      ->condition('status', 1)
      ->condition('field_is_decision', 1)
      ->latestRevision();

    $or = $query->orConditionGroup();
    $or->notExists('field_attachments_checked');
    $or->condition('field_attachments_checked', 0);
    $query->condition($or);

    if ($limit) {
      $query->range('0', $limit);
    }

    $ids = $query->execute();
    $this->logger->info('Total nodes: ' . count($ids));

    $nodes = Node::loadMultiple($ids);
    $operations = [];
    $count = 0;
    foreach ($nodes as $node) {
      if (!$node->hasField('field_decision_native_id') || $node->get('field_decision_native_id')->isEmpty()) {
        continue;
      }

      $native_id = $node->field_decision_native_id->value;
      // Local adjustments for fetching decisions through proxy.
      if (!empty(getenv('AHJO_PROXY_BASE_URL'))) {
        $endpoint = 'decisions/single/' . $native_id;
      }
      else {
        $endpoint = 'decisions/' . $native_id;
      }

      $count++;
      $data = [
        'nid' => $node->id(),
        'native_id' => $node->field_decision_native_id->value,
        'count' => $count,
        'endpoint' => $endpoint,
      ];

      $operations[] = [
        '\Drupal\paatokset_ahjo_proxy\AhjoProxy::updateDecisionAttachments',
        [$data],
      ];
    }

    if (empty($operations)) {
      $this->logger->info('Nothing to import.');
      return;
    }

    batch_set([
      'title' => 'Updating attachments for decisions.',
      'operations' => $operations,
      'finished' => '\Drupal\paatokset_ahjo_proxy\AhjoProxy::finishDecisions',
    ]);

    drush_backend_batch_process();
  }

  /**
   * Parses decision content and motion fields from raw HTML.
   *
   * @param array $options
   *   Additional options for the command.
   *
   * @command ahjo-proxy:parse-decision-content
   *
   * @option limit
   *   Limit processing to certain amount of nodes.
   * @option logic
   *   Determines if motion or content fields are checked.
   *
   * @usage ahjo-proxy:parse-decision-content --limit=50
   *   Parses fields for the first 50 decisions where content is empty.
   *
   * @aliases ap:dc
   */
  public function parseDecisionContents(array $options = [
    'logic' => 'content',
    'limit' => NULL,
  ]): void {
    if (!empty($options['limit'])) {
      $limit = (int) $options['limit'];
    }
    else {
      $limit = 0;
    }

    $this->logger->info('Limiting nodes to: ' . $limit);

    $query = $this->nodeStorage->getQuery()
      ->condition('type', 'decision')
      ->condition('status', 1)
      ->latestRevision();

    if (isset($options['logic']) && $options['logic'] === 'motion') {
      $query->notExists('field_decision_motion_parsed');
      $query->condition('field_decision_motion', '', '<>');
    }
    else {
      $query->notExists('field_decision_content_parsed');
      $query->condition('field_decision_content', '', '<>');
    }

    if ($limit) {
      $query->range('0', $limit);
    }

    $ids = $query->execute();
    $this->logger->info('Total nodes: ' . count($ids));

    $operations = [];
    $count = 0;
    foreach ($ids as $nid) {
      $count++;
      $data = [
        'nid' => $nid,
        'count' => $count,
      ];

      $operations[] = [
        '\Drupal\paatokset_ahjo_proxy\AhjoProxy::parseDecisionItem',
        [$data],
      ];
    }

    if (empty($operations)) {
      $this->logger->info('Nothing to import.');
      return;
    }

    batch_set([
      'title' => 'Parsing data for decisions.',
      'operations' => $operations,
      'finished' => '\Drupal\paatokset_ahjo_proxy\AhjoProxy::finishDecisions',
    ]);

    drush_backend_batch_process();
  }

  /**
   * Sets "is decision" flag for decision nodes.
   *
   * @param array $options
   *   Additional options for the command.
   *
   * @command ahjo-proxy:set-decision-flag
   *
   * @option limit
   *   Limit processing to certain amount of nodes.
   *
   * @usage ahjo-proxy:set-decision-flag --limit=50
   *   Sets decision flag for the first 50 decisions where it wasn't before.
   *
   * @aliases ap:sdf
   */
  public function setDecisionFlag(array $options = [
    'limit' => NULL,
  ]): void {
    if (!empty($options['limit'])) {
      $limit = (int) $options['limit'];
    }
    else {
      $limit = 0;
    }

    $this->logger->info('Limiting nodes to: ' . $limit);

    $query = $this->nodeStorage->getQuery()
      ->condition('type', 'decision')
      ->condition('status', 1)
      ->notExists('field_is_decision')
      ->latestRevision();

    if ($limit) {
      $query->range('0', $limit);
    }

    $ids = $query->execute();
    $this->logger->info('Total nodes: ' . count($ids));

    $operations = [];
    $count = 0;
    foreach ($ids as $nid) {
      $count++;
      $data = [
        'nid' => $nid,
        'count' => $count,
      ];

      $operations[] = [
        '\Drupal\paatokset_ahjo_proxy\AhjoProxy::setDecisionItemFlag',
        [$data],
      ];
    }

    if (empty($operations)) {
      $this->logger->info('Nothing to import.');
      return;
    }

    batch_set([
      'title' => 'Parsing data for decisions.',
      'operations' => $operations,
      'finished' => '\Drupal\paatokset_ahjo_proxy\AhjoProxy::finishDecisions',
    ]);

    drush_backend_batch_process();
  }

  /**
   * Remove policymaker description fields.
   *
   * @param array $options
   *   Additional options for the command.
   *
   * @command ahjo-proxy:remove-policymaker-fields
   *
   * @option limit
   *   Limit processing to certain amount of nodes.
   *
   * @usage ahjo-proxy:remove-policymaker-fields --limit=50
   *   Remove description fields for first 50 nodes.
   *
   * @aliases ap:rpf
   */
  public function removePolicymakerDescriptionFields(array $options = [
    'limit' => NULL,
  ]): void {
    if (!empty($options['limit'])) {
      $limit = (int) $options['limit'];
    }
    else {
      $limit = 0;
    }

    $this->logger->info('Limiting nodes to: ' . $limit);

    $query = $this->nodeStorage->getQuery()
      ->condition('type', 'policymaker')
      ->condition('status', 1)
      ->latestRevision();
    $or = $query->orConditionGroup();
    $or->condition('field_documents_description', '', '<>');
    $or->condition('field_recording_description', '', '<>');
    $or->condition('field_meetings_description', '', '<>');
    $or->condition('field_decisions_description', '', '<>');
    $query->condition($or);

    if ($limit) {
      $query->range('0', $limit);
    }

    $ids = $query->execute();
    $this->logger->info('Total nodes: ' . count($ids));

    $operations = [];
    $count = 0;
    foreach ($ids as $nid) {
      $count++;
      $data = [
        'nid' => $nid,
        'count' => $count,
      ];

      $operations[] = [
        '\Drupal\paatokset_ahjo_proxy\AhjoProxy::removePolicyMakerFieldsFromItem',
        [$data],
      ];
    }

    if (empty($operations)) {
      $this->logger->info('Nothing to import.');
      return;
    }

    batch_set([
      'title' => 'Removing policymaker descriptions.',
      'operations' => $operations,
      'finished' => '\Drupal\paatokset_ahjo_proxy\AhjoProxy::finishDecisions',
    ]);

    drush_backend_batch_process();
  }

  /**
   * Reset decision unique ID fields.
   *
   * @param array $options
   *   Additional options for the command.
   *
   * @command ahjo-proxy:remove-decision-uniqueids
   *
   * @option limit
   *   Limit processing to certain amount of nodes.
   *
   * @usage ahjo-proxy:remove-policymaker-fields --limit=50
   *   Remove unique id fields for first 50 nodes.
   *
   * @aliases ap:ruid
   */
  public function removeDecisionUniqueIdFields(array $options = [
    'limit' => NULL,
  ]): void {
    if (!empty($options['limit'])) {
      $limit = (int) $options['limit'];
    }
    else {
      $limit = 0;
    }

    $this->logger->info('Limiting nodes to: ' . $limit);

    $query = $this->nodeStorage->getQuery()
      ->condition('type', 'decision')
      ->condition('status', 1)
      ->condition('field_unique_id', '', '<>')
      ->latestRevision();

    if ($limit) {
      $query->range('0', $limit);
    }

    $ids = $query->execute();
    $this->logger->info('Total nodes: ' . count($ids));

    $operations = [];
    $count = 0;
    foreach ($ids as $nid) {
      $count++;
      $data = [
        'nid' => $nid,
        'count' => $count,
      ];

      $operations[] = [
        '\Drupal\paatokset_ahjo_proxy\AhjoProxy::removeUniqueIdFromItem',
        [$data],
      ];
    }

    if (empty($operations)) {
      $this->logger->info('Nothing to import.');
      return;
    }

    batch_set([
      'title' => 'Removing policymaker descriptions.',
      'operations' => $operations,
      'finished' => '\Drupal\paatokset_ahjo_proxy\AhjoProxy::finishDecisions',
    ]);

    drush_backend_batch_process();
  }

  /**
   * Resets all meetings so their motions can be processed again.
   *
   * @command ahjo-proxy:reset-meeting-motion-check
   *
   * @aliases ap:rm
   */
  public function resetMeetingsMotionProcessing(): void {
    $query = $this->nodeStorage->getQuery()
      ->condition('type', 'meeting')
      ->condition('status', 1)
      ->condition('field_meeting_agenda_published', 1)
      ->condition('field_meeting_minutes_published', 0)
      ->condition('field_meeting_agenda', '', '<>')
      ->latestRevision();

    $ids = $query->execute();
    $this->logger->info('Total nodes: ' . count($ids));

    $nodes = Node::loadMultiple($ids);
    foreach ($nodes as $node) {
      $node->set('field_agenda_items_processed', 0);
      $node->save();
    }
  }

  /**
   * Resets single meeting by ID so its motions can be processed again.
   *
   * @param string $id
   *   Meeting ID.
   *
   * @command ahjo-proxy:reset-single-meeting-motion-check
   *
   * @aliases ap:rsm
   */
  public function resetSingleMeetingsMotionProcessing(string $id): void {
    $query = $this->nodeStorage->getQuery()
      ->condition('type', 'meeting')
      ->condition('status', 1)
      ->condition('field_meeting_id', $id)
      ->range(0, 1)
      ->latestRevision();

    $ids = $query->execute();
    $this->logger->info('Total nodes: ' . count($ids));

    $nodes = Node::loadMultiple($ids);
    foreach ($nodes as $node) {
      $node->set('field_agenda_items_processed', 0);
      $node->save();
    }
  }

  /**
   * Checks existing decisions for outdated documents.
   *
   * @param array $options
   *   Additional options for the command.
   *
   * @command ahjo-proxy:check-outdated-documents
   *
   * @option limit
   *   Limit processing to certain amount of nodes.
   * @option motions
   *   Check motions instead of decisions.
   *
   * @aliases ap:cod
   */
  public function checkOutdatedDocuments(array $options = [
    'motions' => FALSE,
    'limit' => NULL,
  ]): void {

    if (!empty($options['limit'])) {
      $limit = (int) $options['limit'];
    }
    else {
      $limit = 0;
    }

    $query = $this->nodeStorage->getQuery()
      ->condition('type', 'decision')
      ->condition('status', 1)
      ->condition('field_decision_record', '', '<>')
      ->notExists('field_outdated_document')
      ->latestRevision();

    if ($limit) {
      $query->range(0, $limit);
    }

    $ids = $query->execute();
    $this->logger->info('Total nodes: ' . count($ids));

    $nodes = Node::loadMultiple($ids);
    foreach ($nodes as $node) {
      if (!$this->ahjoProxy->checkDecisionRecord($node)) {
        $node->set('field_outdated_document', 1);
      }
      else {
        $node->set('field_outdated_document', 0);
      }
      $node->save();
    }
  }

  /**
   * Checks motion documents for wrong format.
   *
   * @param array $options
   *   Additional options for the command.
   *
   * @command ahjo-proxy:check-motion-document-format
   *
   * @option limit
   *   Limit processing to certain amount of nodes.
   *
   * @aliases ap:cmdf
   */
  public function checkMotionDocuments(array $options = [
    'motions' => FALSE,
    'limit' => NULL,
  ]): void {

    if (!empty($options['limit'])) {
      $limit = (int) $options['limit'];
    }
    else {
      $limit = 0;
    }

    $query = $this->nodeStorage->getQuery()
      ->condition('type', 'decision')
      ->condition('status', 1)
      ->condition('field_decision_record', '', '<>')
      ->condition('field_is_decision', 0)
      ->latestRevision();

    if ($limit) {
      $query->range(0, $limit);
    }

    $ids = $query->execute();
    $this->logger->info('Total nodes: ' . count($ids));

    $nodes = Node::loadMultiple($ids);
    foreach ($nodes as $node) {
      $record_content = json_decode($node->get('field_decision_record')->value, TRUE);
      if (empty($record_content) || !isset($record_content['Type'])) {
        $node->set('field_decision_record', NULL);
        $node->save();
      }
    }
  }

  /**
   * Checks meeting motion processing to see if motions were generated.
   *
   * @param array $options
   *   Additional options for the command.
   *
   * @command ahjo-proxy:check-motion-processing
   *
   * @option limit
   *   Limit processing to certain amount of nodes.
   *
   * @aliases ap:cmp
   */
  public function checkMeetingsMotionProcessing(array $options = [
    'limit' => NULL,
  ]): void {

    if (!empty($options['limit'])) {
      $limit = (int) $options['limit'];
    }
    else {
      $limit = 0;
    }

    $query = $this->nodeStorage->getQuery()
      ->condition('type', 'meeting')
      ->condition('status', 1)
      ->condition('field_meeting_agenda_published', 1)
      ->condition('field_meeting_minutes_published', 0)
      ->condition('field_agenda_items_processed', 1)
      ->condition('field_meeting_agenda', '', '<>')
      ->latestRevision();

    if ($limit) {
      $query->range(0, $limit);
    }

    $ids = $query->execute();
    $this->logger->info('Total nodes: ' . count($ids));

    $nodes = Node::loadMultiple($ids);
    foreach ($nodes as $node) {
      if (!$this->ahjoProxy->checkMeetingMotions($node)) {
        $this->logger->info('Missing motions for meeting: ' . $node->get('field_meeting_id')->value);
        $node->set('field_agenda_items_processed', 0);
        $node->save();
      }
    }
  }

  /**
   * Checks meeting agenda to see that all decisions are found.
   *
   * @param array $options
   *   Additional options for the command.
   *
   * @command ahjo-proxy:check-decision-processing
   *
   * @option limit
   *   Limit processing to certain amount of nodes.
   * @option queue
   *   Queue decisions to be imported automatically.
   *
   * @aliases ap:cdp
   */
  public function checkMeetingsDecisionProcessing(array $options = [
    'queue' => FALSE,
    'limit' => NULL,
  ]): void {
    if (!empty($options['limit'])) {
      $limit = (int) $options['limit'];
    }
    else {
      $limit = 0;
    }
    if (!empty($options['queue'])) {
      $queue = TRUE;
    }
    else {
      $queue = FALSE;
    }

    $query = $this->nodeStorage->getQuery()
      ->condition('type', 'meeting')
      ->condition('status', 1)
      ->condition('field_meeting_minutes_published', 1)
      ->condition('field_meeting_agenda', '', '<>')
      ->latestRevision();

    if ($limit) {
      $query->range(0, $limit);
    }

    $or = $query->orConditionGroup();
    $or->notExists('field_decisions_checked');
    $or->condition('field_decisions_checked', 0);

    $query->condition($or);

    $ids = $query->execute();
    $this->logger->info('Total nodes: ' . count($ids));

    $nodes = Node::loadMultiple($ids);

    foreach ($nodes as $node) {
      $meeting_id = $node->get('field_meeting_id')->value;

      $missing = $this->ahjoProxy->checkMeetingDecisions($node);
      // Set checked value to true if decisions are found.
      if (empty($missing)) {
        $this->logger->info('No missing decisions for: ' . $meeting_id);
        $node->set('field_decisions_checked', 1);
        $node->save();
      }
      // Add decisions to callback queue if they are not found.
      else {
        $this->logger->info($meeting_id . ' has missing decisions.');
        foreach ($missing as $native_id) {
          if ($queue) {
            $this->writeln(sprintf('Decision added to queue: %s', $native_id));
            $this->ahjoProxy->addItemToAhjoQueue('decisions', $native_id);
          }
          else {
            $this->logger->info(sprintf('-- Missing: %s', $native_id));
          }
        }
      }
    }
  }

  /**
   * Checks decision attachments.
   *
   * @param array $options
   *   Additional options for the command.
   *
   * @command ahjo-proxy:check-decision-attachments
   *
   * @option motions
   *   Check motions instead of decisions.
   * @option limit
   *   Limit processing to certain amount of nodes.
   * @option offset
   *   Limit processing to certain amount of nodes.
   *
   * @aliases ap:cda
   */
  public function checkDecisionAttachments(array $options = [
    'motions' => NULL,
    'limit' => NULL,
    'offset' => NULL,
  ]): void {
    if (!empty($options['motions'])) {
      $motions = TRUE;
    }
    else {
      $motions = FALSE;
    }
    if (!empty($options['limit'])) {
      $limit = (int) $options['limit'];
    }
    else {
      $limit = 0;
    }
    if (!empty($options['offset'])) {
      $offset = (int) $options['offset'];
    }
    else {
      $offset = 0;
    }

    $query = $this->nodeStorage->getQuery()
      ->condition('type', 'decision')
      ->condition('status', 1)
      ->latestRevision();

    if ($motions) {
      $query->condition('field_is_decision', 0);
    }
    else {
      $query->condition('field_is_decision', 1);
    }

    if ($limit) {
      $query->range($offset, $limit);
    }

    $ids = $query->execute();

    $this->logger->info('Total nodes: ' . count($ids));

    $nodes = Node::loadMultiple($ids);
    $table = new Table($this->output());
    $table->setHeaders([
      'NID', 'ID', 'Attachments', 'URLs missing', 'Non-public',
    ]);

    $count = 0;
    $files = 0;
    $classes = [];
    foreach ($nodes as $node) {
      if (!$node->hasField('field_decision_attachments') || $node->get('field_decision_attachments')->isEmpty()) {
        continue;
      }

      $count++;

      $attachment_count = 0;
      $urls_missing = 0;
      $non_public = 0;

      foreach ($node->get('field_decision_attachments') as $field) {
        $data = json_decode($field->value, TRUE);
        $files++;
        $attachment_count++;

        if (!isset($data['FileURI'])) {
          $urls_missing++;
        }

        $publicity_class = NULL;
        if (isset($data['PublicityClass'])) {
          $publicity_class = $data['PublicityClass'];
        }
        if (!in_array($publicity_class, $classes)) {
          $classes[] = $publicity_class;
        }
        if ($publicity_class !== 'Julkinen') {
          $non_public++;
        }
      }

      $table->addRow([
        $node->id(),
        $node->field_decision_native_id->value,
        $attachment_count,
        $urls_missing,
        $non_public,
      ]);
    }
    $table->render();
    $this->logger->info($count . ' nodes with attachments and ' . $files . ' files total.');
    $this->logger->info('Publicity classes: ' . implode(',', $classes));
  }

  /**
   * Resets meeting original date time field.
   *
   * @param array $options
   *   Additional options for the command.
   *
   * @command ahjo-proxy:reset-meeting-orig-date
   *
   * @option limit
   *   Limit processing to certain amount of nodes.
   *
   * @aliases ap:rmod
   */
  public function resetMeetingsOriginalDate(array $options = [
    'limit' => NULL,
  ]): void {
    if (!empty($options['limit'])) {
      $limit = (int) $options['limit'];
    }
    else {
      $limit = 0;
    }

    $query = $this->nodeStorage->getQuery()
      ->condition('type', 'meeting')
      ->condition('status', 1)
      ->notExists('field_meeting_date_original')
      ->latestRevision();

    if ($limit) {
      $query->range('0', $limit);
    }

    $ids = $query->execute();
    $this->logger->info('Total nodes: ' . count($ids));

    $nodes = Node::loadMultiple($ids);
    foreach ($nodes as $node) {
      if (!$node->hasField('field_meeting_date') || $node->get('field_meeting_date')->isEmpty()) {
        continue;
      }
      $meeting_date = $node->get('field_meeting_date')->value;
      $node->set('field_meeting_date_original', $meeting_date);
      $node->save();
    }
  }

  /**
   * Saves motions from meeting agenda items into decision nodes.
   *
   * @param array $options
   *   Additional options for the command.
   *
   * @command ahjo-proxy:get-motions
   *
   * @option update
   *   Update previously created motions instead of just creating new ones.
   * @option localdata
   *   Use only local and placeholder data, doesn't require VPN connection.
   * @option limit
   *   Limit processing to certain amount of meeting nodes.
   * @option offset
   *   Skip the fist x meetings (useful with limit and update parameter).
   *
   * @usage ahjo-proxy:get-motions
   *   Fetches data for new motions from meetings.
   * @usage ahjo-proxy:update-decisions --update
   *   Creates and updates existing motions.
   * @usage ahjo-proxy:get-motions --localdata
   *   Gets data for new motions from locally stored meetings.
   *   Some fields may be limited.
   *
   * @aliases ap:gm
   */
  public function getMotionsFromAgendaItems(array $options = [
    'update' => NULL,
    'localdata' => NULL,
    'limit' => NULL,
    'offset' => NULL,
  ]): void {
    if (!empty($options['update'])) {
      $update_all = TRUE;
    }
    else {
      $update_all = FALSE;
    }

    if (!empty($options['localdata'])) {
      $use_local_data = TRUE;
    }
    else {
      $use_local_data = FALSE;
    }

    if (!empty($options['limit'])) {
      $limit = (int) $options['limit'];
    }
    else {
      $limit = NULL;
    }

    if (!empty($options['offset'])) {
      $offset = (int) $options['offset'];
    }
    else {
      $offset = 0;
    }

    if ($use_local_data) {
      $this->logger->info('Using local data...');
    }
    else {
      $this->logger->info('Fetching data from API...');
    }

    if ($limit) {
      $this->logger->info('Limiting nodes to range: ' . $offset . ' to ' . $limit);
    }

    $query = $this->nodeStorage->getQuery()
      ->condition('type', 'meeting')
      ->condition('status', 1)
      ->condition('field_meeting_agenda_published', 1)
      ->condition('field_meeting_minutes_published', 0)
      ->condition('field_meeting_agenda', '', '<>')
      ->latestRevision();

    if (!$update_all) {
      $query->condition('field_agenda_items_processed', 1, '<>');
    }

    if ($limit) {
      $query->range($offset, $limit);
    }

    $ids = $query->execute();
    $this->logger->info('Total nodes: ' . count($ids));

    $nodes = Node::loadMultiple($ids);

    $operations = [];
    $count = 0;
    foreach ($nodes as $node) {
      if (!$node->hasField('field_meeting_agenda') || $node->get('field_meeting_agenda')->isEmpty()) {
        continue;
      }

      $meeting_data = [
        'meeting_id' => $node->field_meeting_id->value,
        'meeting_number' => $node->field_meeting_sequence_number->value,
        'meeting_date' => $node->field_meeting_date->value,
        'org_id' => $node->field_meeting_dm_id->value,
        'org_name' => $node->field_meeting_dm->value,
      ];

      foreach ($node->get('field_meeting_agenda') as $field) {
        $item = json_decode($field->value, TRUE);

        // Do nothing if PDF record isn't available.
        if (!isset($item['PDF'])) {
          continue;
        }

        // Only create finnish or swedish language motions.
        if (!in_array($item['PDF']['Language'], ['fi', 'sv'])) {
          continue;
        }

        if (!isset($item['PDF']['NativeId'])) {
          continue;
        }
        else {
          $native_id = $item['PDF']['NativeId'];
        }

        $item['PDF']['AgendaPoint'] = $item['AgendaPoint'];

        if (!empty($item['Attachments'])) {
          $attachments = $item['Attachments'];
        }
        else {
          $attachments = [];
        }

        $endpoint = NULL;
        if (!$use_local_data) {
          $endpoint = 'records/' . $native_id;
        }
        $count++;
        $data = [
          'endpoint' => $endpoint,
          'update_all' => $update_all,
          'count' => $count,
          'title' => $item['AgendaItem'],
          'native_id' => $native_id,
          'pdf' => $item['PDF'],
          'html' => $item['HTML'],
          'attachments' => $attachments,
          'meeting_data' => $meeting_data,
        ];

        $operations[] = [
          '\Drupal\paatokset_ahjo_proxy\AhjoProxy::processMotionsItem',
          [$data],
        ];
      }

      // Mark meeting as processed.
      $node->set('field_agenda_items_processed', 1);
      $node->save();
    }

    if (empty($operations)) {
      $this->logger->info('Nothing to import.');
      return;
    }

    $this->logger->info('Amount of items to process: ' . count($operations));

    batch_set([
      'title' => 'Fetching data for motions.',
      'operations' => $operations,
      'finished' => '\Drupal\paatokset_ahjo_proxy\AhjoProxy::finishMotions',
    ]);

    drush_backend_batch_process();
  }

  /**
   * Updates single entity via migration.
   *
   * @param string $endpoint
   *   Additional options for the command.
   * @param string $id
   *   Entity ID.
   *
   * @command ahjo-proxy:update-entity
   *
   * @usage ahjo-proxy:update-entity meetings U0298020221
   *   Updates or creates entity with ID U0298020221.
   *
   * @aliases ap:update
   */
  public function updateSingleEntity(string $endpoint, string $id): void {
    $this->logger->info('Migrating single entity from ' . $endpoint . ' with ID: ' . $id);
    $status = $this->ahjoProxy->migrateSingleEntity($endpoint, $id);
    $this->logger->info('Completed with status: ' . $status);
  }

  /**
   * Generates callback data.
   *
   * @command ahjo-proxy:generate-queue
   *
   * @aliases ap:gq
   */
  public function generateCallbackQueue(): void {
    $data = $this->ahjoProxy->getStatic('callback_test.json');
    $queue = \Drupal::service('queue')->get('ahjo_api_subscriber_queue');
    if (!$queue) {
      $this->logger()->error('Could not load queue.');
    }

    $count = 0;
    foreach ($data as $item) {
      if (empty($item['data']) || !isset($item['data']['id']) || !isset($item['data']['content'])) {
        continue;
      }

      $queue->createItem([
        'id' => $item['data']['id'],
        'content' => (object) $item['data']['content'],
      ]);
      $count++;
    }

    $this->logger()->info('Created ' . $count . ' items.');
  }

  /**
   * List decisions without records.
   *
   * @command ahjo-proxy:list-decisions-without-records
   *
   * @aliases ap:ldwr
   */
  public function listDecisionsWithoutRecord(): void {
    $query = $this->nodeStorage->getQuery()
      ->condition('type', 'decision')
      ->condition('status', 1)
      ->latestRevision();

    $or = $query->orConditionGroup();
    $or->notExists('field_decision_record');
    $or->notExists('field_meeting_date');
    $or->condition('field_meeting_date', '2001-01-01T00:00:00');
    $query->condition($or);

    $ids = $query->execute();
    $this->logger->info('Total nodes: ' . count($ids));
    $nodes = Node::loadMultiple($ids);

    $table = new Table($this->output());
    $table->setHeaders([
      'ID', 'NID', 'UniqueID',
    ]);

    $count = 0;
    foreach ($nodes as $node) {
      $table->addRow([
        $node->field_decision_native_id->value,
        $node->id(),
        $node->field_unique_id->value,
      ]);
      $count++;
    }
    $table->render();
    $this->writeln('Total: ' . $count);
  }

  /**
   * List missing decision makers.
   *
   * @command ahjo-proxy:list-missing-decision-makers
   *
   * @aliases ap:lmdm
   */
  public function listMissingDecisionMakers(): void {
    $database = \Drupal::database();

    $pm_query = $database->select('node__field_policymaker_id', 'field')
      ->fields('field', ['field_policymaker_id_value'])
      ->condition('field.bundle', 'policymaker');
    $pm_results = $pm_query->distinct()->execute()->fetchAll();

    $pm_ids = [];
    foreach ($pm_results as $result) {
      $pm_ids[] = $result->field_policymaker_id_value;
    }

    if (empty($pm_ids)) {
      $this->writeln('No policymaker IDs found.');
      return;
    }

    $table = new Table($this->output());
    $table->setHeaders([
      'Organization ID',
    ]);

    $decision_query = $database->select('node__field_policymaker_id', 'field')
      ->fields('field', ['field_policymaker_id_value'])
      ->condition('field.bundle', 'decision');
    $decision_results = $decision_query->distinct()->execute()->fetchAll();
    $not_found = 0;
    foreach ($decision_results as $result) {
      if (!in_array($result->field_policymaker_id_value, $pm_ids)) {
        $table->addRow([
          $result->field_policymaker_id_value,
        ]);
        $not_found++;
      }
    }

    $table->render();
    $this->writeln('Not found: ' . $not_found);
  }

  /**
   * Check decisionmakers by ID.
   *
   * @param array $options
   *   Options from command line parameters.
   *
   * @command ahjo-proxy:check-decision-makers
   *
   * @option base_url
   *   Base URL to use for comparison check.
   * @option ids
   *   Org IDs to check, separated by a command.
   *
   * @aliases ap:cdm
   */
  public function checkDecisionMakers(array $options = [
    'base_url' => NULL,
    'ids' => NULL,
  ]): void {

    if (!empty($options['base_url'])) {
      $base_url = (string) $options['base_url'];
    }
    else {
      $base_url = NULL;
    }

    if (empty($options['ids'])) {
      $this->writeln('No IDS provided');
      return;
    }
    $ids = explode(',', (string) $options['ids']);
    if (empty($ids)) {
      $this->writeln('No IDS provided');
      return;
    }

    $table = new Table($this->output());
    $table->setHeaders([
      'ID',
      'Found locally',
      'Found in comparison',
    ]);

    $start_time = microtime(TRUE);

    foreach ($ids as $id) {
      $query = $this->nodeStorage->getQuery()
        ->condition('type', 'policymaker')
        ->condition('status', 1)
        ->condition('field_policymaker_id', $id)
        ->latestRevision();

      $ids = $query->execute();

      if (!empty($ids)) {
        $found_locally = '✓';
      }
      else {
        $found_locally = '✗';
      }

      if ($base_url) {
        $response = $this->ahjoProxy->headStatusRequest($base_url . '/' . $id);
        if ($response === 200) {
          $found_in_test = '✓';
        }
        else {
          $found_in_test = '✗';
        }
      }
      else {
        $found_in_test = '-';
      }

      $table->addRow([
        $id,
        $found_locally,
        $found_in_test,
      ]);
    }

    $table->render();

    $end_time = microtime(TRUE);
    $total_time = $end_time - $start_time;
    $this->writeln('Took ' . $total_time . 'seconds');
  }

  /**
   * List decisions by organization ID.
   *
   * @param string $id
   *   Policymaker ID.
   *
   * @command ahjo-proxy:list-decisions-by-org
   *
   * @aliases ap:ldbo
   */
  public function listDecisionsByPolicymakerId(string $id): void {
    $query = $this->nodeStorage->getQuery()
      ->condition('type', 'decision')
      ->condition('status', 1)
      ->condition('field_policymaker_id', $id)
      ->latestRevision();

    $ids = $query->execute();

    $nodes = Node::loadMultiple($ids);
    $table = new Table($this->output());
    $table->setHeaders([
      'ID', 'NID', 'Organization ID',
    ]);

    $count = 0;
    foreach ($nodes as $node) {
      $table->addRow([
        $node->field_decision_native_id->value,
        $node->id(),
        $node->field_policymaker_id->value,
      ]);
      $count++;
    }
    $table->render();
    $this->writeln('Total: ' . $count);
  }

  /**
   * List orphaned motions.
   *
   * @param string $action
   *   Action to take on found orphans. Defaults to list.
   *   - 'list': Only list nodes.
   *   - 'unpublish': Unpublish nodes.
   *   - 'delete': Delete nodes.
   *
   * @command ahjo-proxy:orphaned-motions
   *
   * @aliases ap:om
   */
  public function handleOrphanedMotions(string $action = 'list'): void {
    $query = $this->nodeStorage->getQuery()
      ->condition('type', 'decision')
      ->condition('status', 1)
      ->condition('field_is_decision', 0)
      ->latestRevision();

    $ids = $query->execute();

    $motions = [];
    $nodes = Node::loadMultiple($ids);
    foreach ($nodes as $node) {
      if (!$node->hasField('field_meeting_id') || $node->get('field_meeting_id')->isEmpty()) {
        continue;
      }

      $meeting_id = $node->get('field_meeting_id')->value;

      if (!isset($motions[$meeting_id])) {
        $motions[$meeting_id] = [];
      }
      $motions[$meeting_id][] = $node;
    }

    $meeting_query = $this->nodeStorage->getQuery()
      ->condition('type', 'meeting')
      ->condition('status', 1)
      ->condition('field_meeting_id', array_keys($motions), 'IN')
      ->latestRevision();
    $meeting_ids = $meeting_query->execute();

    $meetings = Node::loadMultiple($meeting_ids);

    $table = new Table($this->output());
    $table->setHeaders([
      'Meeting', 'Title', 'ID',
    ]);

    $orphans = 0;
    $operations = [];
    foreach ($meetings as $meeting) {
      if (!$meeting->hasField('field_meeting_minutes_published')) {
        continue;
      }
      if (!$meeting->get('field_meeting_minutes_published')->value) {
        continue;
      }
      $meeting_id = $meeting->get('field_meeting_id')->value;
      if (!isset($motions[$meeting_id])) {
        continue;
      }

      foreach ($motions[$meeting_id] as $motion) {
        $orphans++;
        if ($action === 'unpublish' || $action === 'delete') {
          $operations[] = $motion;
        }

        $title = substr($motion->title->value, 0, 50);
        $table->addRow([
          $meeting_id,
          $title,
          $motion->get('field_decision_native_id')->value,
        ]);
      }
    }

    $table->render();
    $this->writeln('Total orphans: ' . $orphans);

    if (empty($operations) || ($action !== 'unpublish' && $action !== 'delete')) {
      return;
    }

    if ($action === 'unpublish' && $this->io()->confirm('Are you sure you want to unpublish these nodes?')) {
      foreach ($operations as $node) {
        $node->set('status', 0);
        $node->save();
      }
      $this->writeln('Nodes unpublished.');
    }

    if ($action === 'delete' && $this->io()->confirm('Are you sure you want to delete these nodes?')) {
      $this->nodeStorage->delete($operations);
      $this->writeln('Nodes deleted');
    }
  }

  /**
   * List decisions by meeting ID.
   *
   * @param string $meeting_id
   *   Meeting ID.
   *
   * @command ahjo-proxy:list-meeting-agenda
   *
   * @aliases ap:lma
   */
  public function listMeetingAgenda(string $meeting_id): void {
    $query = $this->nodeStorage->getQuery()
      ->condition('type', 'meeting')
      ->condition('status', 1)
      ->range(0, 1)
      ->condition('field_meeting_id', $meeting_id)
      ->latestRevision();

    $ids = $query->execute();

    $nodes = Node::loadMultiple($ids);
    if (empty($nodes)) {
      $this->writeln(sprintf('No meeting found with ID: %s', $meeting_id));
      return;
    }

    $node = reset($nodes);

    $table = new Table($this->output());
    $table->setHeaders([
      'ID', 'Lang', 'Type', 'Title',
    ]);

    if (!$node->hasField('field_meeting_agenda')) {
      return;
    }

    $table->setHeaderTitle($node->title->value);

    foreach ($node->get('field_meeting_agenda') as $field) {
      $item = json_decode($field->value, TRUE);

      if (!isset($item['PDF'])) {
        continue;
      }

      if (!isset($item['PDF']['NativeId'])) {
        continue;
      }

      $title = substr($item['AgendaItem'], 0, 50);

      $table->addRow([
        $item['PDF']['NativeId'],
        $item['PDF']['Language'],
        $item['PDF']['Type'],
        $title,
      ]);
    }

    $table->render();
  }

  /**
   * Import decisions for meeting, based on ID.
   *
   * @param string $meeting_id
   *   Meeting ID.
   *
   * @command ahjo-proxy:import-meeting-decisions
   *
   * @aliases ap:imd
   */
  public function importMeetingDecisions(string $meeting_id): void {
    $query = $this->nodeStorage->getQuery()
      ->condition('type', 'meeting')
      ->condition('status', 1)
      ->range(0, 1)
      ->condition('field_meeting_id', $meeting_id)
      ->latestRevision();

    $ids = $query->execute();

    $nodes = Node::loadMultiple($ids);
    if (empty($nodes)) {
      $this->writeln(sprintf('No meeting found with ID: %s', $meeting_id));
      return;
    }

    $node = reset($nodes);

    foreach ($node->get('field_meeting_agenda') as $field) {
      $item = json_decode($field->value, TRUE);

      if (!isset($item['PDF'])) {
        continue;
      }

      if (!isset($item['PDF']['NativeId'])) {
        continue;
      }

      if (!isset($item['PDF']['Type']) || $item['PDF']['Type'] !== 'päätös') {
        continue;
      }

      $this->writeln(sprintf('Decision added to queue: %s', $item['PDF']['NativeId']));
      $this->ahjoProxy->addItemToAhjoQueue('decisions', $item['PDF']['NativeId']);
    }
  }

  /**
   * Create basic site structure after fresh install.
   *
   * @command ahjo-proxy:create-site-structure
   *
   * @aliases ap:site
   */
  public function createSiteStructure(): void {
    $pages = [
      [
        'title' => 'Etusivu',
        'path' => '/etusivu',
        'type' => 'landing_page',
        'translations' => [
          'sv' => [
            'title' => 'Framsidan',
            'path' => '/etusivu',
          ],
          'en' => [
            'title' => 'Home',
            'path' => '/etusivu',
          ],
        ],
      ],
      [
        'title' => 'Päättäjät',
        'path' => '/paattajat',
        'type' => 'landing_page',
        'translations' => [
          'sv' => [
            'title' => 'Beslutsfattare',
            'path' => '/beslutsfattare',
          ],
        ],
      ],
      [
        'title' => 'Kokouskalenteri',
        'path' => '/kokouskalenteri',
        'type' => 'landing_page',
        'translations' => [
          'sv' => [
            'title' => 'Möteskalender',
            'path' => '/moteskalender',
          ],
        ],
      ],
      [
        'title' => 'Tietoa Helsingin päätöksenteosta',
        'path' => '/tietoa-paatoksenteosta',
        'type' => 'page',
        'translations' => [
          'sv' => [
            'title' => 'Information om beslutsfattning',
            'path' => '/information-om-beslutsfattning',
          ],
        ],
      ],
      [
        'title' => 'Kuulutukset',
        'path' => '/tietoa-paatoksenteosta/kuulutukset',
        'type' => 'page',
        'translations' => [
          'sv' => [
            'title' => 'Kungörelser',
            'path' => '/information-om-beslutsfattning/kungorelser',
          ],
        ],
      ],
      [
        'title' => 'Sidonnaisuusilmoitukset',
        'path' => '/tietoa-paatoksenteosta/sidonnaisuusilmoitukset',
        'type' => 'page',
        'translations' => [],
      ],
    ];

    $menu = [
      [
        'title' => 'Hae päätöksiä',
        'path' => 'internal:/fi/asia',
        'translations' => [
          'sv' => [
            'title' => 'Arende',
            'path' => 'internal:/sv/arende',
          ],
        ],
      ],
      [
        'title' => 'Päättäjät',
        'path' => 'internal:/fi/paattajat',
        'translations' => [
          'sv' => [
            'title' => 'Beslutsfattare',
            'path' => 'internal:/sv/beslutsfattare',
          ],
        ],
      ],
      [
        'title' => 'Kokouskalenteri',
        'path' => 'internal:/fi/kokouskalenteri',
        'translations' => [
          'sv' => [
            'title' => 'Möteskalender',
            'path' => 'internal:/sv/moteskalender',
          ],
        ],
      ],
      [
        'title' => 'Tietoa päätöksenteosta',
        'path' => 'internal:/fi/tietoa-paatoksenteosta',
        'translations' => [
          'sv' => [
            'title' => 'Information om beslutsfattning',
            'path' => 'internal:/sv/information-om-beslutsfattning',
          ],
        ],
        'children' => [
          [
            'title' => 'Kuulutukset',
            'path' => 'internal:/fi/tietoa-paatoksenteosta/kuulutukset',
            'translations' => [
              'sv' => [
                'title' => 'Kungörelser',
                'path' => 'internal:/sv/information-om-beslutsfattning/kungorelser',
              ],
            ],
          ],
        ],
      ],
    ];

    foreach ($pages as $page) {
      $node = $this->createNode($page['title'], $page['type'], $page['path']);
      if (!$node) {
        $this->writeln(sprintf('Could not create node: %s', $page['title']));
        continue;
      }
      $this->writeln(sprintf('Created node: %s', $page['title']));

      foreach ($page['translations'] as $langcode => $translation) {
        $this->addNodeTranslation($node, $langcode, $translation['title'], $translation['path']);
        $this->writeln(sprintf('...Added %s translation: %s', $langcode, $translation['title']));
      }
    }

    foreach ($menu as $item) {
      $menu_item = $this->addMenuItem($item['title'], $item['path']);

      if (!$menu_item) {
        $this->writeln(sprintf('Could not create menu item: %s', $item['title']));
        continue;
      }
      $this->writeln(sprintf('Created menu item: %s', $item['title']));

      foreach ($item['translations'] as $langcode => $translation) {
        $this->addMenuTranslation($menu_item, $langcode, $translation['title'], $translation['path']);
        $this->writeln(sprintf('...Added %s translation: %s', $langcode, $translation['title']));
      }

      if (empty($item['children'])) {
        continue;
      }

      foreach ($item['children'] as $child) {
        $child_item = $this->addMenuItem($child['title'], $child['path'], $menu_item);

        if (!$child_item) {
          $this->writeln(sprintf('...Could not create menu item: %s', $child['title']));
        }

        $this->writeln(sprintf('...Created child menu item: %s', $child['title']));

        foreach ($child['translations'] as $langcode => $ct_item) {
          $this->addMenuTranslation($child_item, $langcode, $ct_item['title'], $ct_item['path']);
          $this->writeln(sprintf('......Added %s translation: %s', $langcode, $ct_item['title']));
        }
      }
    }
  }

  /**
   * Create or update node and add a custom path.
   *
   * @param string $title
   *   Node title.
   * @param string $type
   *   Node type.
   * @param string $path
   *   Custom path alias.
   *
   * @return \Drupal\node\NodeInterface|null
   *   Created node or NULL if one couldn't be created.
   */
  private function createNode(string $title, string $type, string $path): ?NodeInterface {
    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', $type)
      ->condition('title', $title)
      ->range('0', 1)
      ->latestRevision();
    $nids = $query->execute();

    if (empty($nids)) {
      $node = Node::create([
        'type' => $type,
        'title' => $title,
        'langcode' => 'fi',
      ]);
    }
    else {
      $node = Node::load(reset($nids));
    }

    if (!$node instanceof NodeInterface) {
      return NULL;
    }

    $node->save();

    $path_storage = $this->entityTypeManager->getStorage('path_alias');
    $query = $path_storage->getQuery()
      ->condition('path', '/node/' . $node->id())
      ->range('0', 1)
      ->condition('langcode', 'fi');
    $pids = $query->execute();
    if (empty($pids)) {
      $path_alias = PathAlias::create([
        'path' => '/node/' . $node->id(),
        'alias' => $path,
        'langcode' => 'fi',
      ]);
      $path_alias->save();
    }
    else {
      $path_alias = PathAlias::load(reset($pids));
      $path_alias->set('alias', $path);
      $path_alias->save();
    }

    return $node;
  }

  /**
   * Add a translation for a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Node to add translation to.
   * @param string $langcode
   *   Langcode for translation.
   * @param string $title
   *   Node title.
   * @param string $path
   *   Custom path for translation.
   *
   * @return \Drupal\node\NodeInterface|null
   *   Original node or NULL if adding translation fails.
   */
  private function addNodeTranslation(NodeInterface $node, string $langcode, string $title, string $path): ?NodeInterface {
    if ($node->hasTranslation($langcode)) {
      return $node;
    }
    $node->addTranslation($langcode, [
      'title' => $title,
    ]);

    $node->save();

    $path_storage = $this->entityTypeManager->getStorage('path_alias');
    $query = $path_storage->getQuery()
      ->condition('path', '/node/' . $node->id())
      ->range('0', 1)
      ->condition('langcode', $langcode);
    $pids = $query->execute();
    if (empty($pids)) {
      $path_alias = PathAlias::create([
        'path' => '/node/' . $node->id(),
        'alias' => $path,
        'langcode' => $langcode,
      ]);
      $path_alias->save();
    }
    else {
      $path_alias = PathAlias::load(reset($pids));
      $path_alias->set('alias', $path);
      $path_alias->save();
    }

    return $node;
  }

  /**
   * Add a menu link content item.
   *
   * @param string $title
   *   Menu link title.
   * @param string $path
   *   Menu link URI.
   * @param \Drupal\menu_link_content\MenuLinkContentInterface|null $parent
   *   Parent menu link item, or NULL if this item is at root.
   *
   * @return \Drupal\menu_link_content\MenuLinkContentInterface|null
   *   Created menu link item or NULL if one couldn't be created.
   */
  private function addMenuItem(string $title, string $path, ?MenuLinkContentInterface $parent = NULL): ?MenuLinkContentInterface {
    $menu_storage = $this->entityTypeManager->getStorage('menu_link_content');
    $query = $menu_storage->getQuery()
      ->condition('title', $title)
      ->condition('langcode', 'fi');
    $mids = $query->execute();
    if (empty($mids)) {
      $menu_item = MenuLinkContent::create([
        'menu_name' => 'main',
        'title' => $title,
        'link' => ['uri' => $path],
        'langcode' => 'fi',
        'expanded' => TRUE,
      ]);
    }
    else {
      $menu_item = MenuLinkContent::load(reset($mids));
    }

    if (!$menu_item instanceof MenuLinkContentInterface) {
      return NULL;
    }

    if ($parent instanceof MenuLinkContentInterface) {
      $menu_item->set('parent', 'menu_link_content:' . $parent->uuid());
    }

    $menu_item->save();

    return $menu_item;
  }

  /**
   * Add translation to menu link item.
   *
   * @param \Drupal\menu_link_content\MenuLinkContentInterface $item
   *   Item to add translation to.
   * @param string $langcode
   *   Langcode for translation.
   * @param string $title
   *   Link title.
   * @param string $path
   *   Link URL.
   *
   * @return \Drupal\menu_link_content\MenuLinkContentInterface|null
   *   Original menu link item, or NULL if adding translation fails.
   */
  private function addMenuTranslation(MenuLinkContentInterface $item, string $langcode, string $title, string $path): ?MenuLinkContentInterface {
    if ($item->hasTranslation($langcode)) {
      return $item;
    }

    $item->addTranslation($langcode, [
      'title' => $title,
      'link' => ['uri' => $path],
      'expanded' => TRUE,
    ]);

    $item->save();

    return $item;
  }

  /**
   * Store static files into filesystem.
   *
   * @param string|null $filename
   *   Which file to store.
   *
   * @command ahjo-proxy:store-static-files
   *
   * @usage ahjo-proxy:store-static-files
   *   Stores default static files into filesystem (for debugging migrations).
   *
   * @aliases ap:fs
   */
  public function storeStaticFiles(?string $filename = NULL): void {
    $allowed_static_files = [
      'cases_all.json',
      'cases_latest.json',
      'meetings_all.json',
      'meetings_latest.json',
      'meetings_cancelled.json',
      'decisions_all.json',
      'decisions_latest.json',
      'initiatives_all.json',
      'initiatives_latest.json',
      'resolutions_all.json',
      'resolutions_latest.json',
      'decisionmakers.json',
      'decisionmakers_sv.json',
      'decisionmakers_latest.json',
      'decisionmakers_latest_sv.json',
      'positionsoftrust.json',
      'positionsoftrust_council.json',
      'trustees.json',
      'trustees_council.json',
      'callback_test.json',
    ];

    if ($filename === NULL) {
      $static_files = $allowed_static_files;
    }
    elseif (in_array($filename, $allowed_static_files)) {
      $static_files = [$filename];
    }
    else {
      $this->writeln('Invalid filename.');
      return;
    }

    foreach ($static_files as $file) {
      $file_path = \Drupal::service('extension.list.module')->getPath('paatokset_ahjo_proxy') . '/static/' . $file;
      $file_contents = file_get_contents($file_path);
      if (!empty($file_contents)) {
        $this->fileRepository->writeData($file_contents, 'public://' . $file, FileSystemInterface::EXISTS_REPLACE);
        $this->logger->info('Saved file into public://' . $file);
      }
      else {
        $this->logger->info('Could not load ' . $file);
      }
    }
  }

  /**
   * Set default options.
   *
   * @param string $endpoint
   *   Endpoint to set options for.
   * @param string $dataset
   *   Dataset to set options for.
   * @param array $options
   *   Options from command line parameters.
   *
   * @return array
   *   Set options.
   */
  private function setDefaultOptions(string $endpoint, string $dataset, array $options): array {
    switch ($endpoint) {
      case 'meetings':
        $timestamp_key = 'start';
        $timestamp_key_end = 'end';
        $date_range = strtotime('-4 weeks');
        $date_range_end = strtotime('+2 months');
        break;

      default:
        $timestamp_key = 'handledsince';
        $timestamp_key_end = 'handledbefore';
        $date_range = strtotime("-1 week");
        break;
    }

    if (empty($options['start'])) {
      if ($dataset === 'all') {
        $query_string = $timestamp_key . '=2001-10-01T12:34:45Z';
      }
      else {
        $timestamp = date('Y-m-d\TH:i:s\Z', $date_range);
        $query_string = $timestamp_key . '=' . $timestamp;
      }
    }
    else {
      $query_string = $timestamp_key . '=' . $options['start'];
    }

    if (!empty($options['end'])) {
      $query_string .= '&' . $timestamp_key_end . '=' . $options['end'];
    }
    elseif ($endpoint === 'meetings') {
      $timestamp_end = date('Y-m-d\TH:i:s\Z', $date_range_end);
      $query_string .= '&' . $timestamp_key_end . '=' . $timestamp_end;
    }

    if ($endpoint === 'cases' || $endpoint === 'decisions') {
      $query_string .= '&size=1000&count_limit=1000';
    }

    if (!empty($options['cancelledonly'])) {
      $query_string .= '&cancelledonly=true';
    }

    $options['query_string'] = $query_string;

    return $options;
  }

  /**
   * Get list key for data array.
   *
   * @param string $endpoint
   *   Endpoint to get key for.
   *
   * @return string|null
   *   List key.
   */
  private function getListKey(string $endpoint): ?string {

    switch ($endpoint) {
      case 'cases':
        $key = 'cases';
        break;

      case 'decisions':
        $key = 'decisions';
        break;

      case 'meetings':
        $key = 'meetings';
        break;

      case 'decisionmakers':
        $key = 'decisionMakers';
        break;

      case 'resolutions':
        $key = 'resolutions';
        break;

      case 'initiatives':
        $key = 'initiatives';
        break;

      default:
        $key = $endpoint;
        break;
    }

    return $key;
  }

}
