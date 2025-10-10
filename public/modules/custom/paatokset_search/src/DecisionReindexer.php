<?php

namespace Drupal\paatokset_search;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\paatokset_ahjo_api\Entity\CaseBundle;
use Drupal\search_api\IndexInterface;


/**
 * Reindexes decisions when their parent case is updated.
 */
class DecisionReindexer {

  /**
   * Constructs a new CaseUpdated.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * Reacts to entity changes.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity that was changed.
   */
  public function onEntityChange(EntityInterface $entity): void {
    if ($entity->getEntityTypeId() !== 'node' || $entity->bundle() !== 'case') {
      return;
    }

    $decisions = $this->getRelatedDecisions($entity);
    if (empty($decisions)) {
      return;
    }

    $index = $this->getDecisionsIndex();
    if (!$index) {
      return;
    }

    $datasource_id = 'entity:node';
    foreach ($decisions as $decision) {
      $index->trackItemsUpdated($datasource_id, [$decision['id'] . ':' . $decision['langcode']]);
    }
  }

  /**
   * Gets decision IDs related to a case.
   *
   * @param \Drupal\Core\Entity\EntityInterface $case
   *   The case entity.
   *
   * @return array
   *   An array of decision IDs and their language codes.
   */
  protected function getRelatedDecisions(EntityInterface $case): array {
    if (!$case instanceof CaseBundle) {
      return [];
    }

    $query = $this->entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->condition('type', 'decision')
      ->condition('field_diary_number', $case->get('field_diary_number')->value)
      ->accessCheck(FALSE);

    $decision_ids = $query->execute();

    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($decision_ids);
    $result = [];
    foreach ($nodes as $node) {
      $result[] = [
        'id' => $node->id(),
        'langcode' => $node->language()->getId(),
      ];
    }
    return $result;
  }

  /**
   * Gets the decisions search index.
   *
   * @return \Drupal\search_api\IndexInterface|null
   *   The index or NULL if not found.
   */
  protected function getDecisionsIndex(): ?IndexInterface {
    try {
      /** @var \Drupal\search_api\IndexInterface $index */
      $index = $this->entityTypeManager
        ->getStorage('search_api_index')
        ->load('decisions');
      return $index;
    }
    catch (\Exception $e) {
      return NULL;
    }
  }

}
