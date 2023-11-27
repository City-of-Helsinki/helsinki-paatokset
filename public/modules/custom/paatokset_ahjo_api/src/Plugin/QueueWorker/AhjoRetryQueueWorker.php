<?php

declare(strict_types = 1);

namespace Drupal\paatokset_ahjo_api\Plugin\QueueWorker;

use Drupal\paatokset_ahjo_api\AhjoQueueWorkerBase;

/**
 * Processes cron queue.
 *
 * @QueueWorker(
 *   id = "ahjo_api_retry_queue",
 *   title = @Translation("Ahjo Retry Queue Worker"),
 * )
 */
class AhjoRetryQueueWorker extends AhjoQueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  protected const LOGGER_CHANNEL = 'ahjo_api_retry_queue';

  /**
   * {@inheritDoc}
   */
  protected function getFallbackQueueId(): ?string {
    // End of the line.
    return 'ahjo_api_error_queue';
  }

}
