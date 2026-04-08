<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Queue;

use Drupal\helfi_paatokset_ahjo_api\Plugin\QueueWorker\DecisionRemovedQueueWorker;
use Drupal\paatokset_ahjo_api\Plugin\QueueWorker\AhjoCallbackQueueWorker;

/**
 * An enum to store available queues.
 *
 * The name should match the '$id.$update_type' pattern.
 */
enum SubscriberQueueEnum: string {

  case DecisionRemoved = 'decisions.removed';
  case Default = 'default';

  /**
   * Gets the queue name.
   *
   * @return string
   *   The queue name.
   */
  public function getQueueName(): string {
    return match ($this) {
      self::DecisionRemoved => DecisionRemovedQueueWorker::class,
      self::Default => AhjoCallbackQueueWorker::QUEUE_NAME,
    };
  }

}
