<?php

namespace Drupal\paatokset_search\Plugin\search_api\processor;

use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;

/**
 * Extracts sector info from source JSON.
 *
 * @SearchApiProcessor(
 *   id = "sector_json",
 *   label = @Translation("Sector"),
 *   description = @Translation("Adds sector info from JSON object"),
 *   stages = {
 *     "add_properties" = 0,
 *   },
 *   locked = true,
 *   hidden = true,
 * )
 */
class SectorJSON extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(DatasourceInterface $datasource = NULL) {
    $properties = [];

    if ($datasource) {
      $definition = [
        'label' => $this->t('Sector'),
        'description' => $this->t('Sector value from JSON object'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['sector'] = new ProcessorProperty($definition);
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

      if ($node->getType() !== 'policymaker') {
        return;
      }

      $sectorData = $node->get('field_dm_sector')->value;

      if ($sectorData && $sectorData !== 'null') {
        $parsed = json_decode($sectorData);
        if (isset($parsed->Sector)) {
          $fields = $this->getFieldsHelper()->filterForPropertyPath($item->getFields(), 'entity:node', 'sector');
          foreach ($fields as $field) {
            $field->addValue($parsed->Sector);
          }
        }
      }
    }
  }

}
