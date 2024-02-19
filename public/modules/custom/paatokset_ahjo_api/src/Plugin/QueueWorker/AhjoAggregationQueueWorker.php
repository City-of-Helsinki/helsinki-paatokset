<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\QueueWorker;

use Drupal\paatokset_ahjo_api\AhjoQueueWorkerBase;

/**
 * Processes cron queue.
 *
 * @QueueWorker(
 *   id = "ahjo_api_aggregation_queue",
 *   title = @Translation("Ahjo Retry Queue Worker"),
 * )
 */
class AhjoAggregationQueueWorker extends AhjoQueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  protected const LOGGER_CHANNEL = 'ahjo_api_aggregation_queue';

}
