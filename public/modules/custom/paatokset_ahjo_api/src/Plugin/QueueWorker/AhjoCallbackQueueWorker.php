<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\QueueWorker;

use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\paatokset_ahjo_api\Queue\AhjoQueueWorkerBase;

/**
 * Processes cron queue.
 */
#[QueueWorker(id: 'ahjo_api_subscriber_queue', title: new TranslatableMarkup('Ahjo Callback Queue Worker'))]
class AhjoCallbackQueueWorker extends AhjoQueueWorkerBase {

  /**
   * {@inheritDoc}
   */
  public function getMaxRetryTime(): int {
    // Use shorter retry time for callback queue.
    return (int) (new \DateTime('NOW - 3 HOURS'))->format('U');
  }

}
