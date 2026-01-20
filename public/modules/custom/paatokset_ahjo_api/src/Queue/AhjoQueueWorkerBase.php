<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Queue;

use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\paatokset_ahjo_proxy\AhjoProxy;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes cron queue.
 */
class AhjoQueueWorkerBase extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  final public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected AhjoProxy $ahjoProxy,
    protected AhjoMigrationDriver $migrationDriver,
    protected AhjoQueueManager $queueManager,
    protected LoggerChannelInterface $logger,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('paatokset_ahjo_proxy'),
      $container->get(AhjoMigrationDriver::class),
      $container->get(AhjoQueueManager::class),
      $container->get('logger.channel.paatokset_ahjo_api'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem(mixed $data): void {
    // Process v1 items.
    if (!$data['content'] instanceof Item) {
      $this->processLegacyItem($data);
      return;
    }

    $content = $data['content'];

    $result = $this->migrationDriver->import($content->toMigrateSettings(), $content->migration);

    if ($result != MigrationInterface::RESULT_COMPLETED) {
      $this->logger->warning('Could not process @id from @queue, migration return code = @result', [
        '@id' => $content->id,
        '@queue' => $this->getPluginId(),
        '@result' => $result,
      ]);

      // Check if the item should be moved to the next queue.
      if ($this->canMoveToNextQueue($data)) {
        $return = $this->queueManager->addItemToAhjoQueue(AhjoQueue::from($this->getFallbackQueueId()), $content->id, $content->migration);

        // Return normally so the item is marked as processed from this queue.
        if ($return !== FALSE) {
          return;
        }
      }

      // If the item could not or should not be moved, throw
      // an error to return it to the queue.
      throw new \RuntimeException('Could not process ' . $content->id . ' at ' . $this->getPluginId());
    }

    $this->logger->info('Migrated @id from @queue', [
      '@id' => $content->id,
      '@queue' => $this->getPluginId(),
    ]);
  }

  /**
   * For backwards compatibility.
   */
  public function processLegacyItem(mixed $data): void {
    $operation = $data['content']->updatetype ?? NULL;
    $entity = $data['content']->id ?? NULL;

    if (!$entity || !$operation) {
      $this->logger->info('Empty callback from @queue queue, deleting.', [
        '@queue' => $data['id'],
      ]);
      return;
    }

    if (!$this->ahjoProxy->isOperational()) {
      $this->logger->error('Ahjo Proxy is not operational, suspending.');
      throw new SuspendQueueException('Ahjo Proxy is not operational, suspending.');
    }

    $status = $this->ahjoProxy->migrateSingleEntity($data['id'], $entity);

    if ($status !== 1) {
      // Check if item should be moved to retry or error queue.
      if ($this->moveToErrorQueue($data)) {
        $this->logger->warning('Could not process @id from @queue, migration returned with status: @status. Moved to another queue.', [
          '@id' => $entity,
          '@queue' => $data['id'],
          '@status' => $status,
        ]);

        // Return normally so item is marked as processed from this queue.
        return;
      }
      else {
        // If item could not or should not be moved, throw error to return it.
        // moveToErrorQueue always returns false if processing has failed for
        // items in error queue.
        throw new \Exception(sprintf(
          'Could not process entity %s from %s, migration returned with status: %s.',
          $entity,
          $data['id'],
          $status,
        ));
      }
    }

    // Mark meeting motions to be regenerated after updates.
    if ($data['id'] === 'meetings' && $operation === 'Updated') {
      $this->ahjoProxy->markMeetingMotionsAsUnprocessed($entity);
    }

    $this->logger->info('Migrated @id from @queue as @operation.', [
      '@queue' => $data['id'],
      '@operation' => $operation,
      '@id' => $entity,
    ]);
  }

  /**
   * Get ID of the queue where items are moved in case the processing fails.
   *
   * @return string|null
   *   Queue id. NULL if this items should not be moved anymore.
   */
  protected function getFallbackQueueId(): ?string {
    // Retry items by default.
    return 'ahjo_api_retry_queue';
  }

  /**
   * Get max retry time timestamp. 3 days by default.
   *
   * @return int
   *   Timestamp after which items should now be retried.
   */
  public function getMaxRetryTime(): int {
    return (int) (new \DateTime('NOW - 3 DAYS'))->format('U');
  }

  /**
   * Move item to retry or error queue if enough time has elapsed.
   *
   * Legacy method. See processV2Item.
   *
   * @param mixed $item
   *   Item data to move to another queue.
   *
   * @return bool
   *   TRUE if item was moved, FALSE if not necessary or moving failed.
   */
  protected function moveToErrorQueue(mixed $item): bool {
    assert(isset($item['content']->updatetype));

    $move_to = $this->getFallbackQueueId();

    if (!$this->canMoveToNextQueue($item)) {
      return FALSE;
    }

    assert(isset($item['content']->id));

    // Check if item is already in next queue.
    // If it is, return TRUE here so the duplicate can be removed here too.
    if ($this->ahjoProxy->checkIfItemIsAlreadyInQueue($item['id'], $item['content']->id, $move_to)) {
      return TRUE;
    }

    // Add old queue to operation.
    $operation = $item['content']->updatetype . ' - ' . $this->getPluginId();

    $item_id = $this->ahjoProxy->addItemToAhjoQueue($item['id'], $item['content']->id, $move_to, $operation);
    if ($item_id) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Checks if the item should be moved to the next queue.
   *
   * @param mixed $item
   *   Item data to move to another queue.
   *
   * @return bool
   *   TRUE if item was moved, FALSE if not necessary or moving failed.
   */
  protected function canMoveToNextQueue(mixed $item): bool {
    $moveTo = $this->getFallbackQueueId();

    // getFallbackQueueId returns NULL if the item should not be moved.
    if (empty($moveTo)) {
      return FALSE;
    }

    // Handle legacy items without timestamps.
    if (!isset($item['created'])) {
      $item['created'] = 0;
    }
    elseif ($item['created'] > $this->getMaxRetryTime()) {
      return FALSE;
    }

    return TRUE;
  }

}
