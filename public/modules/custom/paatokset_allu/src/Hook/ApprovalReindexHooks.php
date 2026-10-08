<?php

declare(strict_types=1);

namespace Drupal\paatokset_allu\Hook;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\paatokset_allu\Entity\Approval;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Plugin\search_api\datasource\ContentEntity;

/**
 * Reindexes allu documents when their approvals change.
 *
 * Approvals are indexed as part of their parent document (reverse entity
 * reference + AlluApprovalTypeSplit processor). Search API does not track
 * changes through reverse references, so the parent document must be marked
 * for reindexing manually.
 */
final readonly class ApprovalReindexHooks {

  public function __construct(
    private EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * Implements hook_ENTITY_TYPE_insert().
   */
  #[Hook('paatokset_allu_approval_insert')]
  public function insert(Approval $entity): void {
    $this->onApprovalChange($entity);
  }

  /**
   * Implements hook_ENTITY_TYPE_update().
   */
  #[Hook('paatokset_allu_approval_update')]
  public function update(Approval $entity): void {
    $this->onApprovalChange($entity);

    // Reindex the previously referenced document if the reference changed.
    $original = $entity->getOriginal();
    if ($original instanceof Approval && $original->get('document')->target_id !== $entity->get('document')->target_id) {
      $this->onApprovalChange($original);
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_delete().
   */
  #[Hook('paatokset_allu_approval_delete')]
  public function delete(Approval $entity): void {
    $this->onApprovalChange($entity);
  }

  /**
   * Marks the document referenced by the approval for reindexing.
   *
   * @param \Drupal\paatokset_allu\Entity\Approval $entity
   *   The approval entity.
   */
  private function onApprovalChange(Approval $entity): void {
    $document = $entity->getDocument();
    if (!$document) {
      return;
    }

    $index = $this->getIndex();
    if (!$index || !$index->status()) {
      return;
    }

    $index->trackItemsUpdated('entity:paatokset_allu_document', [
      ContentEntity::formatItemId('paatokset_allu_document', $document->id(), $document->language()->getId()),
    ]);
  }

  /**
   * Gets the allu search index or NULL if not found.
   */
  private function getIndex(): ?IndexInterface {
    try {
      $index = $this->entityTypeManager
        ->getStorage('search_api_index')
        ->load('allu');
      assert(!$index || $index instanceof IndexInterface);
      return $index;
    }
    catch (PluginException) {
      return NULL;
    }
  }

}
