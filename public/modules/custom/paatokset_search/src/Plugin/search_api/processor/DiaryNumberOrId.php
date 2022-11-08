<?php

namespace Drupal\paatokset_search\Plugin\search_api\processor;

use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;

/**
 * Diary number or Unique ID.
 *
 * @SearchApiProcessor(
 *   id = "unique_issue_id",
 *   label = @Translation("Diary number or Unique ID"),
 *   description = @Translation("Adds unique ID if diary number is empty."),
 *   stages = {
 *     "add_properties" = 0,
 *   },
 *   locked = true,
 *   hidden = true,
 * )
 */
class DiaryNumberOrId extends ProcessorPluginBase {

  /**
   * Field with Unique ID.
   */
  const UNIQUE_ID = 'field_decision_native_id';

  /**
   * Field with Diary number.
   */
  const DIARY_NUMBER = 'field_diary_number';

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(DatasourceInterface $datasource = NULL) {
    $properties = [];

    if ($datasource) {
      $definition = [
        'label' => $this->t('Diary number or Unique ID'),
        'description' => $this->t('Adds unique ID if diary number is empty.'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['unique_issue_id'] = new ProcessorProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    $datasourceId = $item->getDatasourceId();
    if ($datasourceId !== 'entity:node') {
      return;
    }

    $node = $item->getOriginalObject()->getValue();
    if ($node->getType() !== 'decision') {
      return;
    }

    $unique_id = NULL;
    if ($node->hasField(self::UNIQUE_ID) && !$node->get(self::UNIQUE_ID)->isEmpty()) {
      $unique_id = $node->get(self::UNIQUE_ID)->value;
    }

    $diary_number = NULL;
    if ($node->hasField(self::DIARY_NUMBER) && !$node->get(self::DIARY_NUMBER)->isEmpty()) {
      $diary_number = $node->get(self::DIARY_NUMBER)->value;
    }

    $fields = $this->getFieldsHelper()->filterForPropertyPath($item->getFields(), 'entity:node', 'unique_issue_id');
    foreach ($fields as $field) {
      if ($diary_number) {
        $field->addValue($diary_number);
      }
      else {
        $field->addValue($unique_id);
      }
    }
  }

}
