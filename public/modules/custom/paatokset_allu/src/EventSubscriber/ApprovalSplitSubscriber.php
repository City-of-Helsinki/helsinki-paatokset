<?php

declare(strict_types=1);

namespace Drupal\paatokset_allu\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\elasticsearch_connector\Event\DeleteParamsEvent;
use Drupal\elasticsearch_connector\Event\IndexParamsEvent;
use Drupal\paatokset_allu\ApprovalType;
use Drupal\paatokset_allu\Entity\Approval;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Splits allu documents into decision and approval documents in Elastic.
 */
final class ApprovalSplitSubscriber implements EventSubscriberInterface {

  /**
   * Search API index ID.
   */
  private const INDEX_ID = 'allu';

  /**
   * Item ID prefix for allu documents.
   */
  private const ITEM_ID_PREFIX = 'entity:paatokset_allu_document/';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      IndexParamsEvent::class => 'onIndexParams',
      DeleteParamsEvent::class => 'onDeleteParams',
    ];
  }

  /**
   * Gets the Elasticsearch document ID of a split document.
   *
   * @param string $itemId
   *   Search API item ID of the allu document.
   * @param int $delta
   *   Split document number, starting from 1.
   *
   * @return string
   *   The split document ID.
   */
  public static function getSplitId(string $itemId, int $delta): string {
    return "{$itemId}__split_{$delta}";
  }

  /**
   * Adds split documents to bulk index requests.
   *
   * @param \Drupal\elasticsearch_connector\Event\IndexParamsEvent $event
   *   The event.
   */
  public function onIndexParams(IndexParamsEvent $event): void {
    if ($event->getOriginalIndexId() !== self::INDEX_ID) {
      return;
    }

    $params = $event->getParams();
    $body = [];

    // Bulk body alternates between action and document source.
    $pairs = array_chunk($params['body'] ?? [], 2);
    $approvalDates = $this->getApprovalDates($pairs);

    foreach ($pairs as [$action, $document]) {
      $id = $action['index']['_id'] ?? NULL;
      if (!is_string($id) || !$this->isDocumentItem($id)) {
        array_push($body, $action, $document);
        continue;
      }

      $types = array_values(array_unique($document['approval_type'] ?? []));

      // The original document represents the decision.
      unset($document['approval_type']);
      array_push($body, $action, $document);

      foreach ($types as $delta => $type) {
        $splitId = self::getSplitId($id, $delta + 1);
        $split = ['approval_type' => [$type], 'search_api_id' => [$splitId]];

        // Approvals are dated by the approval, not by the decision.
        if ($date = $approvalDates[$this->getEntityId($id)][$type] ?? NULL) {
          $split['document_created'] = [$date];
        }

        $body[] = ['index' => ['_id' => $splitId] + $action['index']];
        $body[] = $split + $document;
      }

      // Remove split documents of approval types that no longer exist.
      for ($delta = count($types) + 1; $delta <= count(ApprovalType::cases()); $delta++) {
        $body[] = $this->deleteAction($action['index']['_index'], self::getSplitId($id, $delta));
      }
    }

    $params['body'] = $body;
    $event->setParams($params);
  }

  /**
   * Deletes split documents along with the original document.
   *
   * @param \Drupal\elasticsearch_connector\Event\DeleteParamsEvent $event
   *   The event.
   */
  public function onDeleteParams(DeleteParamsEvent $event): void {
    $params = $event->getParams();
    $body = [];

    foreach ($params['body'] ?? [] as $action) {
      $body[] = $action;

      $id = $action['delete']['_id'] ?? NULL;
      if (!is_string($id) || !$this->isDocumentItem($id)) {
        continue;
      }

      foreach (array_keys(ApprovalType::cases()) as $delta) {
        $body[] = $this->deleteAction($action['delete']['_index'], self::getSplitId($id, $delta + 1));
      }
    }

    $params['body'] = $body;
    $event->setParams($params);
  }

  /**
   * Gets approval dates for the documents in a bulk request.
   *
   * @param array<int, array<int, array<string, mixed>>> $pairs
   *   Bulk body as action and document source pairs.
   *
   * @return array<string, array<string, int>>
   *   The latest approval date keyed by document ID and approval type.
   */
  private function getApprovalDates(array $pairs): array {
    $documentIds = [];
    foreach ($pairs as [$action, $document]) {
      $id = $action['index']['_id'] ?? NULL;
      if (is_string($id) && $this->isDocumentItem($id) && !empty($document['approval_type'])) {
        $documentIds[] = $this->getEntityId($id);
      }
    }

    if (!$documentIds) {
      return [];
    }

    $approvals = $this->entityTypeManager
      ->getStorage('paatokset_allu_approval')
      ->loadByProperties(['document' => $documentIds]);

    $dates = [];
    foreach ($approvals as $approval) {
      assert($approval instanceof Approval);

      $documentId = (string) $approval->get('document')->target_id;
      $type = $approval->get('type')->value;
      $created = (int) $approval->get('created')->value;

      if ($type && $created) {
        $dates[$documentId][$type] = max($dates[$documentId][$type] ?? 0, $created);
      }
    }

    return $dates;
  }

  /**
   * Gets the document entity ID from an allu document item ID.
   */
  private function getEntityId(string $id): string {
    // Item IDs look like "entity:paatokset_allu_document/{id}:{langcode}".
    return explode(':', substr($id, strlen(self::ITEM_ID_PREFIX)))[0];
  }

  /**
   * Checks whether the ID is an allu document item ID.
   */
  private function isDocumentItem(string $id): bool {
    return str_starts_with($id, self::ITEM_ID_PREFIX) && !str_contains($id, '__split_');
  }

  /**
   * Builds a bulk delete action.
   *
   * @return array<string, array<string, string>>
   *   The action.
   */
  private function deleteAction(string $index, string $id): array {
    return ['delete' => ['_index' => $index, '_id' => $id]];
  }

}
