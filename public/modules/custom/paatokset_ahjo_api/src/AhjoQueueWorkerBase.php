<?php

declare(strict_types = 1);

namespace Drupal\paatokset_ahjo_api;

use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Queue\SuspendQueueException;

/**
 * Processes cron queue.
 */
class AhjoQueueWorkerBase extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * Ahjo proxy service.
   *
   * @var \Drupal\paatokset_ahjo_proxy\AhjoProxy
   */
  protected $ahjoProxy;

  /**
   * Queue name.
   *
   * @var string
   */
  protected string $queueName;

  protected const VERBOSE_LOGGING = TRUE;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->ahjoProxy = $container->get('paatokset_ahjo_proxy');
    $instance->logger = $container->get('logger.factory')->get('ahjo_api_subscriber_queue');
    $instance->queueName = 'ahjo_api_default_queue';
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($item) {
    if (isset($item['content']->updatetype)) {
      $operation = $item['content']->updatetype;
    }
    else {
      $operation = NULL;
    }

    if (isset($item['content']->id)) {
      $entity = $item['content']->id;
    }
    else {
      $entity = NULL;
    }

    if (!$entity || !$operation) {
      if (self::VERBOSE_LOGGING) {
        $this->logger->info('Empty callback from @queue queue, deleting.', [
          '@queue' => $item['id'],
        ]);
      }
      return;
    }

    if (!$this->ahjoProxy->isOperational()) {
      $this->logger->error('Ahjo Proxy is not operational, suspending.');
      throw new SuspendQueueException('Ahjo Proxy is not operational, suspending.');
    }

    $status = $this->ahjoProxy->migrateSingleEntity($item['id'], $entity);

    if ($status !== 1) {
      // Check if item should be moved to retry or error queue.
      if ($this->moveToErrorQueue($item)) {
        $this->logger->warning('Could not process @id from @queue, migration returned with status: @status. Moved to another queue.', [
          '@id' => $entity,
          '@queue' => $item['id'],
          '@status' => $status,
        ]);

        // Return normally so item is marked as processed from this queue.
        return;
      }
      else {
        // If item could not or should not be moved, throw error to return it.
        throw new \Exception(sprintf(
          'Could not process entity %s from %s, migration returned with status: %s.',
          $entity,
          $item['id'],
          $status,
        ));
      }
    }

    // Mark meeting motions to be regenerated after updates.
    if ($item['id'] === 'meetings' && $operation === 'Updated') {
      $this->ahjoProxy->markMeetingMotionsAsUnprocessed($entity);
    }

    $this->logger->info('Migrated @id from @queue as @operation.', [
      '@queue' => $item['id'],
      '@operation' => $operation,
      '@id' => $entity,
    ]);
  }

  /**
   * Move item to retry or error queue if enough time has elapsed.
   *
   * @param mixed $item
   *   Item data to move to another queue.
   *
   * @return bool
   *   TRUE if item was moved, FALSE if not necessary or moving failed.
   */
  protected function moveToErrorQueue(mixed $item): bool {
    // Don't move anything from error queue.
    if ($this->queueName === 'ahjo_api_error_queue') {
      return FALSE;
    }

    // Move items to retry queue and from there to error queue.
    if ($this->queueName === 'ahjo_api_retry_queue') {
      $move_to = 'ahjo_api_error_queue';
    }
    else {
      $move_to = 'ahjo_api_retry_queue';
    }

    // Allow 3 hours of retries for callbacks, 3 days for other queues.
    if ($this->queueName === 'ahjo_api_callback_queue') {
      $max_time = (int) (new \DateTime('NOW - 3 HOURS'))->format('U');
    }
    else {
      $max_time = (int) (new \DateTime('NOW - 3 DAYS'))->format('U');
    }

    // Move all old items without timestamps.
    if (!isset($item['created'])) {
      $item['created'] = 0;
    }
    if ($item['created'] > $max_time) {
      return FALSE;
    }

    // Check if item is already in next queue.
    // If it is, return TRUE here so the duplicate can be removed here too.
    if ($this->ahjoProxy->checkIfItemIsAlreadyInQueue($item['id'], $item['content']->id, $move_to)) {
      return TRUE;
    }

    // Add old queue to update type label.
    if (isset($item['content']->updatetype)) {
      $operation = $item['content']->updatetype;
    }
    else {
      $operation = 'Moved';
    }
    $operation .= ' - ' . $this->queueName;

    $item_id = $this->ahjoProxy->addItemToAhjoQueue($item['id'], $item['content']->id, $move_to, $operation);
    if ($item_id) {
      return TRUE;
    }

    return FALSE;
  }

}
