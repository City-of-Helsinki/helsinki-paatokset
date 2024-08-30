<?php

declare(strict_types=1);

namespace Drupal\paatokset_search\Plugin\search_api\processor;

use Drupal\node\NodeInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;

/**
 * Excludes inactive decisionmaker nodes from indexes.
 *
 * @SearchApiProcessor(
 *   id = "inactive_decisionmakers",
 *   label = @Translation("Inactive decisionmakers"),
 *   description = @Translation("Exclude inactive decisionmaker nodes from being indexed."),
 *   stages = {
 *     "alter_items" = 0,
 *   },
 * )
 */
class InactiveDecisionmakers extends ProcessorPluginBase {

  /**
   * Field that indicates if decisionmaker is active.
   */
  const ACTIVE_INDICATOR = 'field_policymaker_existing';

  /**
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index) {
    foreach ($index->getDatasources() as $datasource) {
      $entity_type_id = $datasource->getEntityTypeId();
      if (!$entity_type_id) {
        continue;
      }

      // Only active on decisionmaker nodes.
      $bundles = $datasource->getBundles();
      if ($entity_type_id === 'node' && in_array('policymaker', array_keys($bundles))) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function alterIndexedItems(array &$items) {
    /** @var \Drupal\search_api\Item\ItemInterface $item */
    foreach ($items as $item_id => $item) {
      $object = $item->getOriginalObject()->getValue();

      if (!$object instanceof NodeInterface) {
        continue;
      }

      // Only act if active field exists.
      if (!$object->hasField(self::ACTIVE_INDICATOR)) {
        continue;
      }
      // Remove from index if active field is empty or has been set to false.
      if ($object->get(self::ACTIVE_INDICATOR)->isEmpty() || !$object->get(self::ACTIVE_INDICATOR)->value) {
        unset($items[$item_id]);
      }
    }
  }

}
