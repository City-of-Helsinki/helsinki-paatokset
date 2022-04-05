<?php

declare(strict_types = 1);

namespace Drupal\paatokset_ahjo_proxy\Commands;

use Drush\Commands\DrushCommands;
use Drupal\file\FileRepositoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\paatokset_ahjo_proxy\AhjoProxy;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeStorageInterface;
use Drupal\node\Entity\Node;

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
    'retry' => NULL,
    'filename' => NULL,
    'append' => NULL,
  ]): void {

    $allowed_datasets = [
      'all',
      'latest',
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
   * @option start
   *   Custom timestamp for fetching data.
   * @option end
   *   Custom timestamp for fetching data.
   * @option filename
   *   Filename to use instead of default. Can be used to split/batch results.
   *
   * @usage ahjo-proxy:get decisionmakers
   *   Stores all decisionmakers into decisionmakers.json
   * @usage ahjo-proxy:get decisionmakers --start=021100VH1 --filename=decisionmakers_021100VH1.json
   *   Stores decisionmakers under the 021100VH1 organisation.
   *
   * @aliases ap:get
   */
  public function get(string $endpoint, array $options = [
    'start' => NULL,
    'end' => NULL,
    'filename' => NULL,
  ]): void {
    $data = [];
    $list_key = $this->getListKey($endpoint);

    $query_string = '';
    if (!empty($options['start'])) {
      $query_string .= 'start=' . $options['start'];
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
      $meeting_data = [
        'meeting_id' => $node->field_meeting_id->value,
        'meeting_number' => $node->field_meeting_sequence_number->value,
        'meeting_date' => $node->field_meeting_date->value,
        'org_id' => $node->field_meeting_dm_id->value,
        'org_name' => $node->field_meeting_dm->value,
      ];

      foreach ($node->get('field_meeting_agenda') as $field) {
        $item = json_decode($field->value, TRUE);

        // Only create finnish language motions.
        if (!isset($item['PDF']) || $item['PDF']['Language'] !== 'fi') {
          continue;
        }

        if (!isset($item['PDF']['NativeId'])) {
          continue;
        }
        else {
          $native_id = $item['PDF']['NativeId'];
        }

        $item['PDF']['AgendaPoint'] = $item['AgendaPoint'];

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
   * Store static files into filesystem.
   *
   * @command ahjo-proxy:store-static-files
   *
   * @usage ahjo-proxy:store-static-files
   *   Stores default static files into filesystem (for debugging migrations).
   *
   * @aliases ap:fs
   */
  public function storeStaticFiles() {
    $static_files = [
      'cases_all.json',
      'cases_latest.json',
      'meetings_all.json',
      'meetings_latest.json',
      'decisions_all.json',
      'decisions_latest.json',
      'initiatives_all.json',
      'initiatives_latest.json',
      'resolutions_all.json',
      'resolutions_latest.json',
      'decisionmakers.json',
      'decisionmakers_sv.json',
      'positionsoftrust.json',
      'positionsoftrust_council.json',
      'trustees.json',
      'trustees_council.json',
      'callback_test.json',
    ];

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
        break;

      default:
        $timestamp_key = 'handledsince';
        $timestamp_key_end = 'handledbefore';
        break;
    }

    if (empty($options['start'])) {
      if ($dataset === 'all') {
        $query_string = $timestamp_key . '=2001-10-01T12:34:45Z';
      }
      else {
        $week_ago = strtotime("-1 week");
        $timestamp = date('Y-m-d\TH:i:s\Z', $week_ago);
        $query_string = $timestamp_key . '=' . $timestamp;
      }
    }
    else {
      $query_string = $timestamp_key . '=' . $options['start'];
    }

    if (!empty($options['end'])) {
      $query_string .= '&' . $timestamp_key_end . '=' . $options['end'];
    }

    if ($endpoint === 'cases' || $endpoint === 'decisions') {
      $query_string .= '&size=1000&count_limit=1000';
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
