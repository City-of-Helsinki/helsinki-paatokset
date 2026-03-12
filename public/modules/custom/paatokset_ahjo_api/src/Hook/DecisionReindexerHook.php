<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Hook;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\paatokset_ahjo_api\Entity\CaseBundle;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Plugin\search_api\datasource\ContentEntity;

/**
 * Reindexes decisions when their parent case is updated.
 */
final readonly class DecisionReindexerHook {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * Implements hook_entity_update().
   */
  #[Hook('entity_update')]
  public function update(EntityInterface $entity): void {
    $this->onEntityChange($entity);
  }

  /**
   * Implements hook_entity_insert().
   */
  #[Hook('entity_insert')]
  public function insert(EntityInterface $entity): void {
    $this->onEntityChange($entity);
  }

  /**
   * Implements hook_entity_delete().
   */
  #[Hook('entity_delete')]
  public function delete(EntityInterface $entity): void {
    $this->onEntityChange($entity);
  }

  /**
   * Reacts to entity changes.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity that was changed.
   */
  private function onEntityChange(EntityInterface $entity): void {
    if (!$entity instanceof CaseBundle) {
      return;
    }

    /** @todo find a way to use CaseBundle::getAllDecisions. */
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
      $index->trackItemsUpdated($datasource_id, [
        ContentEntity::formatItemId('node', $decision['id'], $decision['langcode']),
      ]);
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
  private function getRelatedDecisions(EntityInterface $case): array {
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

    $nodes = $this->entityTypeManager
      ->getStorage('node')
      ->loadMultiple($decision_ids);

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
   * Gets the decisions search index or NULL if not found.
   */
  private function getDecisionsIndex(): ?IndexInterface {
    try {
      /** @var \Drupal\search_api\IndexInterface $index */
      $index = $this->entityTypeManager
        ->getStorage('search_api_index')
        ->load('decisions');
      return $index;
    }
    catch (PluginException) {
      return NULL;
    }
  }

}
