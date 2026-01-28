<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\QueueWorker;

use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\paatokset_ahjo_api\Queue\AhjoQueueWorkerBase;

/**
 * Processes cron queue.
 */
#[QueueWorker(id: 'ahjo_api_retry_queue', title: new TranslatableMarkup('Ahjo Retry Queue Worker'))]
class AhjoRetryQueueWorker extends AhjoQueueWorkerBase {

  /**
   * {@inheritDoc}
   */
  protected function getFallbackQueueId(): ?string {
    return 'ahjo_api_error_queue';
  }

}
