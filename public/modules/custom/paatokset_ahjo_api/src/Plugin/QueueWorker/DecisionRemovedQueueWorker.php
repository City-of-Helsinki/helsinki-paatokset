<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\QueueWorker;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\paatokset_ahjo_api\Entity\Decision;
use Drupal\paatokset_ahjo_api\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Processes decision removed callbacks.
 *
 * Deletes the decision node(s) targeted by an Ahjo "Removed" callback and,
 * if that was the last decision belonging to the case, deletes the case too.
 */
#[QueueWorker(
  id: self::class,
  title: new TranslatableMarkup('Subscriber callback: Decision removed'),
)]
final class DecisionRemovedQueueWorker extends QueueWorkerBase {

  /**
   * Constructs a new instance.
   *
   * @phpstan-param array<string, mixed> $configuration
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected Connection $connection,
    #[Autowire(service: 'logger.channel.paatokset_ahjo_api')] protected LoggerChannelInterface $logger,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function processItem(mixed $data): void {
    $content = $data['content'] ?? NULL;
    if (!is_object($content)) {
      return;
    }

    $nativeId = isset($content->id) ? (string) $content->id : '';
    $caseId = isset($content->caseId) ? (string) $content->caseId : '';

    if ($nativeId === '') {
      return;
    }

    $nativeId = Decision::bracketNativeId($nativeId);
    $storage = $this->entityTypeManager->getStorage('node');

    $transaction = $this->connection->startTransaction();

    try {
      // Find the decision node(s) matching the native id. No status or
      // language filter, so unpublished nodes and every language version
      // (stored as separate nodes) are removed. accessCheck is disabled
      // because this runs in the background without a real user.
      $decisionIds = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', 'decision')
        ->condition('field_decision_native_id', $nativeId)
        ->execute();

      $diaryNumber = $caseId;

      if (!empty($decisionIds)) {
        $decisions = $storage->loadMultiple($decisionIds);

        $storage->delete($decisions);
        $this->logger->info('Removed @count decision node(s) for native id @id.', [
          '@count' => count($decisions),
          '@id' => $nativeId,
        ]);
      }
      else {
        $this->logger->info('No decision node found for native id @id, attempting case cleanup.', [
          '@id' => $nativeId,
        ]);
      }

      // Without a diary number we cannot locate the case for cleanup.
      if ($diaryNumber === '') {
        $this->logger->warning('Could not resolve case diary number for removed decision @id.', [
          '@id' => $nativeId,
        ]);
        return;
      }

      // If the case has no remaining decisions, remove the case node too.
      $remaining = (int) $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', 'decision')
        ->condition('field_diary_number', $diaryNumber)
        ->count()
        ->execute();

      if ($remaining > 0) {
        return;
      }

      $caseIds = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', 'case')
        ->condition('field_diary_number', $diaryNumber)
        ->execute();

      if (empty($caseIds)) {
        $this->logger->info('No case node found for diary number @number to clean up.', [
          '@number' => $diaryNumber,
        ]);
        return;
      }

      $cases = $storage->loadMultiple($caseIds);
      $storage->delete($cases);
      $this->logger->info('Removed @count case node(s) for diary number @number after the last decision was removed.', [
        '@count' => count($cases),
        '@number' => $diaryNumber,
      ]);
    }
    catch (\Exception $e) {
      $transaction->rollBack();
      throw $e;
    }
  }

}
