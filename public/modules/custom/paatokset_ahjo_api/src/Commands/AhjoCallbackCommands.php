<?php

declare(strict_types = 1);

namespace Drupal\paatokset_ahjo_api\Commands;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Helper\Table;

/**
 * Ahjo Callback drush commands.
 *
 * @package Drupal\paatokset_ahjo_api\Commands
 */
class AhjoCallbackCommands extends DrushCommands {

  private const QUEUE_NAME = 'ahjo_api_subscriber_queue';
  private const AGGREGATION_QUEUE_NAME = 'ahjo_api_aggregation_queue';
  private const RETRY_QUEUE_NAME = 'ahjo_api_retry_queue';
  private const ERROR_QUEUE_NAME = 'ahjo_api_error_queue';

  /**
   * Ahjo callback queue.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  /**
   * Ahjo retry queue.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $retryQueue;

  /**
   * Ahjo aggregation queue.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $aggregationQueue;

  /**
   * Ahjo error queue.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $errorQueue;

  /**
   * Constructor for Ahjo Callback commands.
   *
   * @param \Drupal\Core\Queue\QueueFactory $queueFactory
   *   Queue service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   Logger service.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(
    QueueFactory $queueFactory,
    LoggerChannelFactoryInterface $logger_factory,
    private Connection $database,
  ) {
    $this->queue = $queueFactory->get(self::QUEUE_NAME);
    $this->retryQueue = $queueFactory->get(self::RETRY_QUEUE_NAME);
    $this->aggregationQueue = $queueFactory->get(self::AGGREGATION_QUEUE_NAME);
    $this->errorQueue = $queueFactory->get(self::ERROR_QUEUE_NAME);
    $this->setLogger($logger_factory->get('ahjo_api_subscriber'));
  }

  /**
   * List Ahjo queue items.
   *
   * @param \Drupal\Core\Queue\QueueInterface $queue
   *   Queue to list items for.
   * @param string $queue_name
   *   Queue name, used for printing command after items.
   * @param string|null $name
   *   Sub-queue name (ahjo entity).
   */
  protected function listQueueItems(QueueInterface $queue, string $queue_name, ?string $name = NULL): void {
    $table = new Table($this->output());
    $table->setHeaders([
      'Queue', 'ID', 'Time', 'Operation', 'Entity',
    ]);

    $count = 0;
    $items = [];
    $ids = [];
    $operations = [];
    while ($item = $queue->claimItem()) {
      $items[] = $item;

      if ($name !== NULL && $item->data['id'] !== $name) {
        continue;
      }

      $count++;

      if (isset($item->data['content']->updatetype)) {
        $operation = $item->data['content']->updatetype;
      }
      else {
        $operation = NULL;
      }

      if (!isset($ids[$item->data['id']])) {
        $ids[$item->data['id']] = [];
      }

      if (!in_array($operation, $operations)) {
        $operations[] = $operation;
      }

      if (isset($item->data['content']->id)) {
        $entity = $item->data['content']->id;
      }
      else {
        $entity = NULL;
      }

      if (isset($item->data['created'])) {
        $created = date('Y-m-d H:i:s', (int) $item->data['created']);
      }
      else {
        $created = date('Y-m-d H:i:s', (int) $item->created) . ' (no timestamp)';
      }

      if ($entity && !in_array($entity, $ids[$item->data['id']])) {
        $ids[$item->data['id']][] = $entity;
      }

      $table->addRow([
        $item->data['id'],
        $item->item_id,
        $created,
        $operation,
        $entity,
      ]);
    }

    // Release claimed items.
    foreach ($items as $item) {
      $queue->releaseItem(($item));
    }

    $table->render();

    foreach ($ids as $key => $value) {
      $this->writeln('Queue: ' . $key . ' has ' . count($value) . ' unique IDs.');
    }
    $this->writeln('Total: ' . $count);
    $this->writeln('Operations: ' . implode(', ', $operations) . '.');
    $this->writeln('Run with: drush queue:run ' . $queue_name);
  }

  /**
   * Clear Ahjo queue items.
   *
   * @param \Drupal\Core\Queue\QueueInterface $queue
   *   Queue to clear items for.
   * @param string|null $name
   *   Sub-queue name (ahjo entity).
   */
  protected function clearQueueItems(QueueInterface $queue, ?string $name = NULL): void {
    if ($name) {
      $this->output()->writeln('Clearing queue: ' . $name);
    }
    else {
      $this->output()->writeln('Clearing all queues.');
    }

    if (!$this->io()->confirm('Are you sure?')) {
      return;
    }

    $count = 0;
    $items = [];
    while ($item = $queue->claimItem()) {
      if ($name !== NULL && $item->data['id'] !== $name) {
        $items[] = $item;
        continue;
      }

      $queue->deleteItem($item);
      $count++;
    }

    // Release claimed items that weren't deleted.
    foreach ($items as $item) {
      $queue->releaseItem(($item));
    }
    $this->output()->writeln('Deleted ' . $count . ' items.');
  }

  /**
   * List callback queue contents.
   *
   * @param string|null $name
   *   Queue name. NULL for all.
   *
   * @command ahjo-callback:list-callback-queue
   *
   * @usage ahjo-callback:list-callback-queue meetings
   *   Lists queue contents for "meetings" endpoint.
   *
   * @aliases ac:l
   */
  public function listCallbackQueue(?string $name = NULL): void {
    $this->listQueueItems($this->queue, self::QUEUE_NAME, $name);
  }

  /**
   * List retry queue contents.
   *
   * @param string|null $name
   *   Queue name. NULL for all.
   *
   * @command ahjo-callback:list-retry-queue
   *
   * @usage ahjo-callback:list-retry-queue meetings
   *   Lists queue contents for "meetings" endpoint.
   *
   * @aliases ac:lr
   */
  public function listRetryQueue(?string $name = NULL): void {
    $this->listQueueItems($this->retryQueue, self::RETRY_QUEUE_NAME, $name);
  }

  /**
   * List aggregation queue contents.
   *
   * @param string|null $name
   *   Queue name. NULL for all.
   *
   * @command ahjo-callback:list-aggregation-queue
   *
   * @usage ahjo-callback:list-aggregation-queue meetings
   *   Lists queue contents for "meetings" endpoint.
   *
   * @aliases ac:la
   */
  public function listAggregationQueue(?string $name = NULL): void {
    $this->listQueueItems($this->aggregationQueue, self::AGGREGATION_QUEUE_NAME, $name);
  }

  /**
   * List error queue contents.
   *
   * @param string|null $name
   *   Queue name. NULL for all.
   *
   * @command ahjo-callback:list-error-queue
   *
   * @usage ahjo-callback:list-error-queue meetings
   *   Lists queue contents for "meetings" endpoint.
   *
   * @aliases ac:le
   */
  public function listErrorQueue(?string $name = NULL): void {
    $this->listQueueItems($this->errorQueue, self::ERROR_QUEUE_NAME, $name);
  }

  /**
   * Deletes single item from queue.
   *
   * @param string $id
   *   Item ID.
   *
   * @command ahjo-callback:delete-item
   *
   * @usage ahjo-callback:delete-item 1234
   *   Delete item with ID 1234.
   *
   * @aliases ac:di
   */
  public function deleteCallbackItem(string $id): void {
    if (!$item = $this->loadItem(self::QUEUE_NAME, $id)) {
      $this->logger()->error('Unable to load item with id: ' . $id);
      return;
    }

    try {
      $this->queue->deleteItem($item);
      $this->logger()->info('Removed item ' . $id . ' from the queue.');
    }
    catch (\Exception $e) {
      $this->logger()->error('Error removing item from queue: ' . $e->getMessage());
    }
  }

  /**
   * Deletes single item from queue.
   *
   * @param string $id
   *   Item ID.
   *
   * @command ahjo-callback:delete-retry-item
   *
   * @usage ahjo-callback:delete-retry-item 1234
   *   Delete item with ID 1234.
   *
   * @aliases ac:di-retry
   */
  public function deleteRetryItem(string $id): void {
    if (!$item = $this->loadItem(self::RETRY_QUEUE_NAME, $id)) {
      $this->logger()->error('Unable to load item with id: ' . $id);
      return;
    }

    try {
      $this->retryQueue->deleteItem($item);
      $this->logger()->info('Removed item ' . $id . ' from the queue.');
    }
    catch (\Exception $e) {
      $this->logger()->error('Error removing item from queue: ' . $e->getMessage());
    }
  }

  /**
   * Deletes single item from queue.
   *
   * @param string $id
   *   Item ID.
   *
   * @command ahjo-callback:delete-aggregation-item
   *
   * @usage ahjo-callback:delete-aggregation-item 1234
   *   Delete item with ID 1234.
   *
   * @aliases ac:di-agg
   */
  public function deleteAggregationItem(string $id): void {
    if (!$item = $this->loadItem(self::AGGREGATION_QUEUE_NAME, $id)) {
      $this->logger()->error('Unable to load item with id: ' . $id);
      return;
    }

    try {
      $this->aggregationQueue->deleteItem($item);
      $this->logger()->info('Removed item ' . $id . ' from the queue.');
    }
    catch (\Exception $e) {
      $this->logger()->error('Error removing item from queue: ' . $e->getMessage());
    }
  }

  /**
   * Deletes single item from queue.
   *
   * @param string $id
   *   Item ID.
   *
   * @command ahjo-callback:delete-error-item
   *
   * @usage ahjo-callback:delete-error-item 1234
   *   Delete item with ID 1234.
   *
   * @aliases ac:di-err
   */
  public function deleteErrorItem(string $id): void {
    if (!$item = $this->loadItem(self::ERROR_QUEUE_NAME, $id)) {
      $this->logger()->error('Unable to load item with id: ' . $id);
      return;
    }

    try {
      $this->aggregationQueue->deleteItem($item);
      $this->logger()->info('Removed item ' . $id . ' from the queue.');
    }
    catch (\Exception $e) {
      $this->logger()->error('Error removing item from queue: ' . $e->getMessage());
    }
  }

  /**
   * Clear queue contents.
   *
   * @param string|null $name
   *   Queue name. NULL for all.
   *
   * @command ahjo-callback:clear-queue
   *
   * @usage ahjo-callback:clear-queue meetings
   *   Clear queue contents for "meetings" endpoint.
   *
   * @aliases ac:clear
   */
  public function clearCallbackQueue(?string $name = NULL): void {
    $this->clearQueueItems($this->queue, $name);
  }

  /**
   * Clear queue contents.
   *
   * @param string|null $name
   *   Queue name. NULL for all.
   *
   * @command ahjo-callback:clear-retry-queue
   *
   * @usage ahjo-callback:clear-retry-queue meetings
   *   Clear queue contents for "meetings" endpoint.
   *
   * @aliases ac:clear-retry
   */
  public function clearRetryQueue(?string $name = NULL): void {
    $this->clearQueueItems($this->retryQueue, $name);
  }

  /**
   * Clear queue contents.
   *
   * @param string|null $name
   *   Queue name. NULL for all.
   *
   * @command ahjo-callback:clear-aggregation-queue
   *
   * @usage ahjo-callback:clear-aggregation-queue meetings
   *   Clear queue contents for "meetings" endpoint.
   *
   * @aliases ac:clear-agg
   */
  public function clearAggregationQueue(?string $name = NULL): void {
    $this->clearQueueItems($this->aggregationQueue, $name);
  }

  /**
   * Clear queue contents.
   *
   * @param string|null $name
   *   Queue name. NULL for all.
   *
   * @command ahjo-callback:clear-error-queue
   *
   * @usage ahjo-callback:clear-error-queue meetings
   *   Clear queue contents for "meetings" endpoint.
   *
   * @aliases ac:clear-err
   */
  public function clearErrorQueue(?string $name = NULL): void {
    $this->clearQueueItems($this->aggregationQueue, $name);
  }

  /**
   * Load a specified Ahjo API queue item from the database.
   *
   * @param string $queue_name
   *   Queue name.
   * @param string $item_id
   *   The item id to load.
   *
   * @return mixed
   *   Result of the database query loading the queue item.
   */
  private function loadItem(string $queue_name, string $item_id) {
    // Load the specified queue item from the queue table.
    $query = $this->database->select('queue', 'q')
      ->fields('q', ['item_id', 'name', 'data', 'expire', 'created'])
      ->condition('q.item_id', $item_id)
      ->condition('q.name', $queue_name)
      // Item id should be unique.
      ->range(0, 1);

    return $query->execute()->fetchObject();
  }

}
