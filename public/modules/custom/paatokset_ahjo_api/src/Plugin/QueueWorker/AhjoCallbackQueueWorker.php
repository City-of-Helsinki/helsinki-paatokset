<?php

declare(strict_types = 1);

namespace Drupal\paatokset_ahjo_api\Plugin\QueueWorker;

use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Queue\SuspendQueueException;

/**
 * Processes cron queue.
 *
 * @QueueWorker(
 *   id = "ahjo_api_subscriber_queue",
 *   title = @Translation("Ahjo Callback Queue Worker"),
 * )
 */
class AhjoCallbackQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

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

  private const VERBOSE_LOGGING = TRUE;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->ahjoProxy = $container->get('paatokset_ahjo_proxy');
    $instance->logger = $container->get('logger.factory')->get('ahjo_api_subscriber_queue');
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
      if (self::VERBOSE_LOGGING) {
        $this->logger->warning('Could not process entity @id from @queue, migration returned with status: @status.', [
          '@id' => $entity,
          '@queue' => $item['id'],
          '@status' => $status,
        ]);
      }

      throw new \Exception(sprintf(
        'Could not process entity %s from %s, migration returned with status: %s.',
        $entity,
        $item['id'],
        $status,
      ));
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

}
