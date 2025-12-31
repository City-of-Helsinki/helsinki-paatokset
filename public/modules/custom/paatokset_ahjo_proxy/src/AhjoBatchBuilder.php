<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_proxy;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Meeting batch builder.
 */
readonly class AhjoBatchBuilder {

  public function __construct(
    #[Autowire(service: 'logger.channel.paatokset_ahjo_proxy')]
    private LoggerInterface $logger,
    private EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * Saves motions from meeting agenda items into decision nodes.
   *
   * @param bool $update_all
   *   Update previously created motions instead of just creating new ones.
   * @param bool $use_local_data
   *   Use only local and placeholder data, doesn't require VPN connection.
   * @param int|null $limit
   *   Limit processing to certain amount of meeting nodes.
   * @param int $offset
   *   Skip the fist x meetings (useful with limit and update parameter).
   *
   * @return \Drupal\Core\Batch\BatchBuilder|null
   *   NULL if there is nothing to do.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function getMotionsFromAgendaItemsBatch(
    bool $update_all = FALSE,
    bool $use_local_data = FALSE,
    ?int $limit = NULL,
    int $offset = 0,
  ): ?BatchBuilder {
    if ($use_local_data) {
      $this->logger->info('Using local data...');
    }
    else {
      $this->logger->info('Fetching data from API...');
    }

    if ($limit) {
      $this->logger->info('Limiting nodes to range: ' . $offset . ' to ' . $limit);
    }

    $nodeStorage = $this->entityTypeManager->getStorage('node');
    $query = $nodeStorage->getQuery()
      ->accessCheck(TRUE)
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

    $nodes = $nodeStorage->loadMultiple($ids);

    $operations = [];
    $count = 0;
    /** @var \Drupal\Core\Entity\FieldableEntityInterface[] $nodes */
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

        if (isset($item['PDF']['VersionSeriesId'])) {
          $version_id = $item['PDF']['VersionSeriesId'];
        }
        else {
          $version_id = NULL;
        }

        $item['PDF']['AgendaPoint'] = $item['AgendaPoint'];

        $endpoint = NULL;
        if (!$use_local_data) {
          $endpoint = 'records/' . $native_id;
        }

        if (!empty(getenv('AHJO_PROXY_BASE_URL'))) {
          $agenda_endpoint = 'agenda-item/' . $meeting_data['meeting_id'] . '/' . $native_id;
        }
        else {
          $agenda_endpoint = 'meetings/' . $meeting_data['meeting_id'] . '/agendaitems/' . $native_id;
        }

        $count++;
        $data = [
          'endpoint' => $endpoint,
          'agenda_endpoint' => $agenda_endpoint,
          'update_all' => $update_all,
          'count' => $count,
          'title' => $item['AgendaItem'],
          'native_id' => $native_id,
          'version_id' => $version_id,
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
      return NULL;
    }

    $this->logger->info('Amount of items to process: ' . count($operations));

    $batch_builder = (new BatchBuilder())
      ->setTitle('Fetching data for motions.')
      ->setFinishCallback('\Drupal\paatokset_ahjo_proxy\AhjoProxy::finishMotions');

    foreach ($operations as $operation) {
      $batch_builder->addOperation($operation[0], $operation[1]);
    }

    return $batch_builder;
  }

}
