<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\search_api\processor;

use Drupal\node\NodeInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;

/**
 * Handle hidden decision content.
 *
 * @SearchApiProcessor(
 *   id = "hidden_decisions",
 *   label = @Translation("Handle hidden decisions"),
 *   description = @Translation("Hide content for hidden decision."),
 *   stages = {
 *     "preprocess_index" = 100,
 *   },
 * )
 */
class HiddenDecisions extends ProcessorPluginBase {

  /**
   * Field that indicates hidden content.
   */
  const HIDE_INDICATOR = 'field_hide_decision_content';

  /**
   * Fields to hide.
   */
  const HIDDEN_FIELDS = [
    'decision_content',
    'decision_motion',
  ];

  /**
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index) {
    foreach ($index->getDatasources() as $datasource) {
      $entity_type_id = $datasource->getEntityTypeId();
      if (!$entity_type_id) {
        continue;
      }

      // Only active on decision nodes.
      $bundles = $datasource->getBundles();
      if ($entity_type_id === 'node' && in_array('decision', array_keys($bundles))) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessIndexItems(array $items) {
    /** @var \Drupal\search_api\Item\ItemInterface $item */
    foreach ($items as $item) {
      $object = $item->getOriginalObject()->getValue();
      if (!$object instanceof NodeInterface) {
        continue;
      }

      // Only act if hide field exists and isn't empty.
      if (!$object->hasField(self::HIDE_INDICATOR) || $object->get(self::HIDE_INDICATOR)->isEmpty()) {
        continue;
      }
      // Check that hide toggle is set to true.
      if (!$object->get(self::HIDE_INDICATOR)->value) {
        continue;
      }

      // Set content fields to NULL.
      foreach (self::HIDDEN_FIELDS as $field_id) {
        $item->setField($field_id, NULL);
      }
    }
  }

}
