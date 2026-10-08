<?php

declare(strict_types=1);

namespace Drupal\paatokset_allu\EventSubscriber;

use Drupal\elasticsearch_connector\Event\DeleteParamsEvent;
use Drupal\elasticsearch_connector\Event\IndexParamsEvent;
use Drupal\paatokset_allu\ApprovalType;
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
    foreach (array_chunk($params['body'] ?? [], 2) as [$action, $document]) {
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
        $body[] = ['index' => ['_id' => $splitId] + $action['index']];
        $body[] = ['approval_type' => [$type], 'search_api_id' => [$splitId]] + $document;
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
