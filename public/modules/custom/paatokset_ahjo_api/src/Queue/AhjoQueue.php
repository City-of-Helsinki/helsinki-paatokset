<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Queue;

/**
 * Enum for ahjo queues.
 *
 * The enum values match queue worker plugin names.
 *
 * @see \Drupal\paatokset_ahjo_api\Plugin\QueueWorker\AhjoAggregationQueueWorker
 * @see \Drupal\paatokset_ahjo_api\Plugin\QueueWorker\AhjoRetryQueueWorker
 * @see \Drupal\paatokset_ahjo_api\Plugin\QueueWorker\AhjoErrorQueueWorker
 */
enum AhjoQueue: string {
  case AggregationQueue = 'ahjo_api_aggregation_queue';
  case RetryQueue = 'ahjo_api_retry_queue';
  case ErrorQueue = 'ahjo_api_error_queue';
}
