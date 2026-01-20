<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\QueueWorker;

use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\paatokset_ahjo_api\Queue\AhjoQueueWorkerBase;

/**
 * Processes cron queue.
 */
#[QueueWorker(id: 'ahjo_api_aggregation_queue', title: new TranslatableMarkup('Ahjo Aggregation Queue Worker'))]
class AhjoAggregationQueueWorker extends AhjoQueueWorkerBase {
}
