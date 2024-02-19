<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\QueueWorker;

use Drupal\paatokset_ahjo_api\AhjoQueueWorkerBase;

/**
 * Processes cron queue.
 *
 * @QueueWorker(
 *   id = "ahjo_api_error_queue",
 *   title = @Translation("Ahjo Error Queue Worker"),
 * )
 */
class AhjoErrorQueueWorker extends AhjoQueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  protected const LOGGER_CHANNEL = 'ahjo_api_error_queue';

  /**
   * {@inheritDoc}
   */
  protected function getFallbackQueueId(): ?string {
    // End of the line.
    return NULL;
  }

}
