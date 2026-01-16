<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Queue;

use Drupal\Core\Database\Connection;
use Drupal\Core\Queue\QueueFactory;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * Ahjo queue manager.
 *
 * Ahjo items are added to a queue, which runs a single
 * item migration in the background. If the migration
 * fails, the item is moved to retry or error queue.
 *
 * @see \Drupal\paatokset_ahjo_api\AhjoQueueWorkerBase
 */
class AhjoQueueManager implements LoggerAwareInterface {

  use LoggerAwareTrait;

  public function __construct(
    private readonly QueueFactory $queue,
    private readonly Connection $database,
  ) {
  }

  /**
   * Add item to the queue for processing.
   *
   * @return string|false|null
   *   NULL if item was already in queue, FALSE if the adding failed.
   */
  public function addItemToAhjoQueue(AhjoQueue $queue, string $id, string $type): string|false|null {
    // Attempt to reduce duplicates.
    if ($this->checkIfItemIsAlreadyInQueue($queue, $id, $type)) {
      return NULL;
    }

    $this->logger?->info("Adding item $id to " . $queue->value);

    return $this->queue
      ->get($queue->value)
      // @todo the whole queue item should be DTO class.
      // However, the legacy implementation expects part of
      // it to be an array.
      ->createItem([
        'id' => 'v2',
        'content' => new Item($id, $type),
        'created' => (int) (new \DateTimeImmutable())->format('U'),
        'request' => [],
      ]);
  }

  /**
   * Check if an item has already been added to the queue.
   *
   * Used to reduce duplicates.
   */
  private function checkIfItemIsAlreadyInQueue(AhjoQueue $queue, string $id, string $type): bool {
    // Load the specified queue item from the queue table.
    $count = $this->database->select('queue', 'q')
      ->condition('q.name', $queue->value)
      // This query is copied from AhjoProxy.
      // The code seems quite flaky, the data field is a PHP serialized object.
      ->condition('q.data', '%' . $this->database->escapeLike($id) . '%', 'LIKE')
      ->condition('q.data', '%' . $this->database->escapeLike($type) . '%', 'LIKE')
      ->countQuery()
      ->execute()
      ->fetchField();

    return $count > 0;
  }

}
