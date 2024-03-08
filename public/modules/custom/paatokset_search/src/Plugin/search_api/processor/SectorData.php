<?php

namespace Drupal\paatokset_search\Plugin\search_api\processor;

use Drupal\node\NodeInterface;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;

/**
 * Adds decisionmaker related sector data to index as json.
 *
 * @SearchApiProcessor(
 *    id = "sector_data",
 *    label = @Translation("Decisionmaker sector data"),
 *    description = @Translation("Sector data."),
 *    stages = {
 *      "add_properties" = 0,
 *    }
 * )
 */
class SectorData extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(DatasourceInterface $datasource = NULL) {
    $properties = [];

    if ($datasource) {
      $definition = [
        'label' => $this->t('Decisionmaker search dropdown title'),
        'description' => $this->t('Combine decisionmaker policymaker_id, organization_name and organization_above_name.'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['sector_data'] = new ProcessorProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    $datasourceId = $item->getDataSourceId();
    if ($datasourceId === 'entity:node') {
      $node = $item->getOriginalObject()->getValue();

      if (
        !$node instanceof NodeInterface ||
        $node->getType() !== 'decision'
      ) {
        return;
      }

      $data = [];
      if ($node->hasField('field_policymaker_id')) {
        $data['id'] = $node->get('field_policymaker_id')->value;
      }

      $sector_field = 'field_sector_name';
      $dm = null;
      $dmId = $node->hasField('field_policymaker_id') ? $node->get('field_policymaker_id')->value : null;
      if ($dmId) {
        $nodes = \Drupal::entityTypeManager()
          ->getStorage('node')
          ->loadByProperties([
            'type' => 'policymaker',
            'field_policymaker_id' => $dmId,
          ]);

        $dm = reset($nodes);
      }

      $data['sector'] = ['fi' => null, 'en' => null, 'sv' => null];

      if ($dm) {
        $original_translation = $dm->hasTranslation('fi') ? $dm->getTranslation('fi') : $dm;
        $languages = ['fi', 'en', 'sv'];

        foreach ($languages as $langcode) {
          $dm = $dm->hasTranslation($langcode) ? $dm->getTranslation($langcode) : $original_translation;
          if ($dm && $dm->hasTranslation($langcode)) {
            $dm = $dm->getTranslation($langcode);
            $data['sector'][$langcode] = $dm->get($sector_field)->value;
          }
        }
      }

      $fields = $this->getFieldsHelper()
        ->filterForPropertyPath($item->getFields(), 'entity:node', 'sector_data');
      if (isset($fields['sector_data'])) {
        $fields['sector_data']->addValue(
          json_encode($data)
        );
      }

    }
  }

}
