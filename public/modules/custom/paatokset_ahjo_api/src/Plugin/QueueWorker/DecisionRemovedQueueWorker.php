<?php

declare(strict_types=1);

namespace Drupal\helfi_paatokset_ahjo_api\Plugin\QueueWorker;

use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\paatokset_ahjo_api\Queue\QueueWorkerBase;

#[QueueWorker(
  id: self::class,
  title: new TranslatableMarkup('Subscriber callback: Decision removed'),
)]
final class DecisionRemovedQueueWorker extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem(mixed $data): void {
  }

}
