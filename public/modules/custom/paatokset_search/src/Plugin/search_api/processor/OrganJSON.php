<?php

namespace Drupal\paatokset_search\Plugin\search_api\processor;

use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;

/**
 * Adds the item's view count to the indexed data.
 *
 * @SearchApiProcessor(
 *   id = "organ_json",
 *   label = @Translation("Organ"),
 *   description = @Translation("Adds organ info from JSON object"),
 *   stages = {
 *     "add_properties" = 0,
 *   },
 *   locked = true,
 *   hidden = true,
 * )
 */
class OrganJSON extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(DatasourceInterface $datasource = NULL) {
    $properties = [];

    if ($datasource) {
      $definition = [
        'label' => $this->t('Organ'),
        'description' => $this->t('Organ value from JSON object'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['organ'] = new ProcessorProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    $datasourceId = $item->getDatasourceId();
    if ($datasourceId == 'entity:node') {
      $node = $item->getOriginalObject()->getValue();
      $organData = $node->get('field_dm_org_above')->value;

      if ($organData && $organData !== 'null') {
        $parsed = json_decode($organData);
        if (isset($parsed->organizations) && isset($parsed->organizations[0]->Name)) {
          $fields = $this->getFieldsHelper()->filterForPropertyPath($item->getFields(), 'entity:node', 'organ');
          foreach ($fields as $field) {
            $field->addValue($parsed->organizations[0]->Name);
          }
        }
      }
    }
  }

}
