<?php

declare(strict_types = 1);

namespace Drupal\paatokset_ahjo_api\Commands;

use Drush\Commands\DrushCommands;
use Drupal\Core\Database\Connection;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\paatokset_ahjo_proxy\AhjoProxy;
use Symfony\Component\Console\Helper\Table;

/**
 * Ahjo Callback drush commands.
 *
 * @package Drupal\paatokset_ahjo_api\Commands
 */
class AhjoCallbackCommands extends DrushCommands {

  private const QUEUE_NAME = 'ahjo_api_subscriber_queue';
  private const ORG_QUEUE_NAME = 'ahjo_api_org_queue';

  /**
   * Ahjo callback queue.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  /**
   * Ahjo organization chart queue.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $orgQueue;

  /**
   * Ahjo proxy service.
   *
   * @var \Drupal\paatokset_ahjo_proxy\AhjoProxy
   */
  protected $ahjoProxy;

  /**
   * Queue Factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The database.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructor for Ahjo Callback commands.
   *
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   Queue service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   Logger service.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\paatokset_ahjo_proxy\AhjoProxy $ahjo_proxy
   *   Ahjo Proxy service.
   */
  public function __construct(QueueFactory $queue_factory, LoggerChannelFactoryInterface $logger_factory, Connection $database, AhjoProxy $ahjo_proxy) {
    $this->queueFactory = $queue_factory;
    $this->queue = $this->queueFactory->get(self::QUEUE_NAME);
    $this->orgQueue = $this->queueFactory->get(self::ORG_QUEUE_NAME);
    $this->logger = $logger_factory->get('ahjo_api_subscriber');
    $this->database = $database;
    $this->ahjoProxy = $ahjo_proxy;
  }

  /**
   * List queue contents.
   *
   * @param string|null $name
   *   Queue name. NULL for all.
   *
   * @command ahjo-callback:list-queue
   *
   * @usage ahjo-callback:list-queue meetings
   *   Lists queue contents for "meetings" endpoint.
   *
   * @aliases ac:l
   */
  public function listCallbackQueue(?string $name = NULL): void {
    $table = new Table($this->output());
    $table->setHeaders([
      'Queue', 'ID', 'Time', 'Operation', 'Entity',
    ]);

    $count = 0;
    $items = [];
    $ids = [];
    $operations = [];
    while ($item = $this->queue->claimItem()) {
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

      if ($entity && !in_array($entity, $ids[$item->data['id']])) {
        $ids[$item->data['id']][] = $entity;
      }

      $table->addRow([
        $item->data['id'],
        $item->item_id,
        date('Y-m-d H:i:s', (int) $item->created),
        $operation,
        $entity,
      ]);
    }

    // Release claimed items.
    foreach ($items as $item) {
      $this->queue->releaseItem(($item));
    }

    $table->render();

    foreach ($ids as $key => $value) {
      $this->writeln('Queue: ' . $key . ' has ' . count($value) . ' unique IDs.');
    }
    $this->writeln('Total: ' . $count);
    $this->writeln('Operations: ' . implode(', ', $operations) . '.');
    $this->writeln('Run with: drush queue:run ' . self::QUEUE_NAME);
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
  public function deleteItem(string $id): void {
    if (!$item = $this->loadItem($id)) {
      $this->logger()->error('Unable to load item with id: ' . $id);
      return;
    }

    try {
      $this->queue->deleteItem($item);
      $this->logger()->success('Removed item ' . $id . ' from the queue.');
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
  public function clearQueue(?string $name = NULL): void {
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
    while ($item = $this->queue->claimItem()) {
      if ($name !== NULL && $item->data['id'] !== $name) {
        $items[] = $item;
        continue;
      }

      $this->queue->deleteItem($item);
      $count++;
    }

    // Release claimed items that weren't deleted.
    foreach ($items as $item) {
      $this->queue->releaseItem(($item));
    }
    $this->output()->writeln('Deleted ' . $count . ' items.');
  }

  /**
   * Load a specified Ahjo API queue item from the database.
   *
   * @param string $item_id
   *   The item id to load.
   *
   * @return mixed
   *   Result of the database query loading the queue item.
   */
  private function loadItem(string $item_id) {
    // Load the specified queue item from the queue table.
    $query = $this->database->select('queue', 'q')
      ->fields('q', ['item_id', 'name', 'data', 'expire', 'created'])
      ->condition('q.item_id', $item_id)
      ->condition('q.name', self::QUEUE_NAME)
      // Item id should be unique.
      ->range(0, 1);

    return $query->execute()->fetchObject();
  }

  /**
   * Initialize organization chart queue.
   *
   * @param string $start_id
   *   Org ID to start from.
   * @param int $max_steps
   *   Maximum amount of steps.
   * @param string $langcode
   *   Language to get org data with.
   *
   * @command ahjo-callback:start-org-queue
   *
   * @usage ahjo-callback:start-org-queue 00001 3
   *   Get first three levels of organization data.
   * @usage ahjo-callback:start-org-queue 00001 3 sv
   *   Get Swedish translations of the first three levels.
   *
   * @aliases ac:sorg
   */
  public function startOrgQueue(string $start_id, int $max_steps = 5, string $langcode = 'fi') {
    $data = [
      'id' => $start_id,
      'step' => 0,
      'max_steps' => $max_steps,
      'langcode' => $langcode,
    ];

    $item_id = $this->orgQueue->createItem($data);

    if ($item_id) {
      $this->writeln(sprintf('Started org queue from %s with %d steps.', $start_id, $max_steps));
      $this->logger->info('Added item to org chart queue: @id (@langcode), @step out of @max_steps).', [
        '@id' => $start_id,
        '@langcode' => $langcode,
        '@step' => 0,
        '@max_steps' => $max_steps,
      ]);
    }
    else {
      $this->writeln('Could not start org chart queue');
    }
  }

  /**
   * List organization chart queue contents.
   *
   * @command ahjo-callback:list-org-queue
   *
   * @aliases ac:org
   */
  public function listOrgQueue(): void {
    $table = new Table($this->output());
    $table->setHeaders([
      'OrgID', 'ID', 'Lang', 'Time', 'Step', 'Max steps',
    ]);

    $count = 0;
    $items = [];
    $ids = [];

    while ($item = $this->orgQueue->claimItem()) {
      $items[] = $item;

      $count++;

      if (isset($item->data['step'])) {
        $step = $item->data['step'];
      }
      else {
        $step = NULL;
      }

      if (isset($item->data['max_steps'])) {
        $max_steps = $item->data['max_steps'];
      }
      else {
        $max_steps = NULL;
      }

      if (!in_array($item->data['id'], $ids)) {
        $ids[] = $item->data['id'];
      }

      $table->addRow([
        $item->data['id'],
        $item->item_id,
        $item->data['langcode'],
        date('Y-m-d H:i:s', (int) $item->created),
        $step,
        $max_steps,
      ]);
    }

    // Release claimed items.
    foreach ($items as $item) {
      $this->queue->releaseItem(($item));
    }

    $table->render();
    $this->writeln('Queue has ' . count($ids) . ' unique IDs.');
    $this->writeln('Total: ' . $count);
    $this->writeln('Run with: drush queue:run ' . self::ORG_QUEUE_NAME);
  }

  /**
   * Clear organization chart queue contents.
   *
   * @command ahjo-callback:clear-org-queue
   *
   * @aliases ac:corg
   */
  public function clearOrgQueue(): void {
    $this->output()->writeln('Clearing organization chart queue.');

    if (!$this->io()->confirm('Are you sure?')) {
      return;
    }

    $count = 0;
    while ($item = $this->orgQueue->claimItem()) {
      $this->orgQueue->deleteItem($item);
      $count++;
    }

    $this->output()->writeln('Deleted ' . $count . ' items.');
  }

}
