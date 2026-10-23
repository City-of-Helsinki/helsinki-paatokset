<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\QueueWorker;

use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\paatokset_ahjo_api\Queue\AhjoQueueWorkerBase;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * Processes cron queue.
 */
#[QueueWorker(id: 'ahjo_api_error_queue', title: new TranslatableMarkup('Ahjo Error Queue Worker'))]
class AhjoErrorQueueWorker extends AhjoQueueWorkerBase implements LoggerAwareInterface {

  use LoggerAwareTrait;

  /**
   * {@inheritDoc}
   */
  public function processItem(mixed $data): void {
    $content = $data['content'];
    if ($content && is_object($content)) {
      $id = $content->id ?? '';
      $this->logger->alert("Item ID $id is being processed by error queue worker. Please check the error queue for any items.");
    }

    parent::processItem($data);
  }

  /**
   * {@inheritDoc}
   */
  protected function getFallbackQueueId(): ?string {
    // End of the line.
    return NULL;
  }

}
