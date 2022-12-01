<?php

namespace Drupal\paatokset_search\Plugin\search_api\processor;

use Drupal\node\NodeInterface;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;

/**
 * Gets top category code from fields.
 *
 * @SearchApiProcessor(
 *   id = "top_category_id",
 *   label = @Translation("Top Category ID"),
 *   description = @Translation("Adds top category ID and name from fields"),
 *   stages = {
 *     "add_properties" = 0,
 *   },
 *   locked = true,
 *   hidden = true,
 * )
 */
class TopCategoryCode extends ProcessorPluginBase {

  /**
   * Field with classification code.
   */
  const CODE_FIELD = 'field_classification_code';

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(DatasourceInterface $datasource = NULL) {
    $properties = [];

    if ($datasource) {
      $definition = [
        'label' => $this->t('Top category code'),
        'description' => $this->t('Top category code from decision node.'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['top_category_code'] = new ProcessorProperty($definition);
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
    if (!$node instanceof NodeInterface || $node->getType() !== 'decision') {
      return;
    }

    if (!$node->hasField(self::CODE_FIELD) || $node->get(self::CODE_FIELD)->isEmpty()) {
      return;
    }

    // Parse top category code from classification.
    $code = $node->get(self::CODE_FIELD)->value;
    $bits = explode(' ', (string) $code);
    $top_code = array_shift($bits);

    $fields = $this->getFieldsHelper()->filterForPropertyPath($item->getFields(), 'entity:node', 'top_category_code');
    foreach ($fields as $field) {
      $field->addValue($top_code);
    }
  }

}
