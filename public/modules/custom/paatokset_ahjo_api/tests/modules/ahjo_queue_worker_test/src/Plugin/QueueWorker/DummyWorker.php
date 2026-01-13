<?php

namespace Drupal\ahjo_queue_worker_test\Plugin\QueueWorker;

use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\paatokset_ahjo_api\Queue\AhjoQueueWorkerBase;

/**
 * Dummy queue plugin.
 */
#[QueueWorker(id: 'ahjo_queue_worker_test')]
class DummyWorker extends AhjoQueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public const LOGGER_CHANNEL = 'dummy_logger_channel';

}
