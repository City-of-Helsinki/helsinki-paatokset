<?php

namespace Drupal\paatokset_search\Plugin\search_api\processor;

use Drupal\node\NodeInterface;
use Drupal\paatokset_ahjo_api\Service\TrusteeService;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;

/**
 * Combines policymaker_id, organization_name and organization_above_name
 *
 * @SearchApiProcessor(
 *    id = "decisionmaker_searchfield_data",
 *    label = @Translation("Decisionmaker Searchfield Data"),
 *    description = @Translation("Combines policymaker_id id, organization_name and organization_above_name."),
 *    stages = {
 *      "add_properties" = 0,
 *    },
 *    locked = true,
 *    hidden = true,
 * )
 */
class DecisionmakerSearchfieldData extends ProcessorPluginBase {

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
      $properties['decisionmaker_searchfield_data'] = new ProcessorProperty($definition);
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

      if (!$node instanceof NodeInterface) {
        return;
      }

      $data = [];
      if ($node->hasField('field_policymaker_id')) {
        $data['id'] = $node->get('field_policymaker_id')->value;
      }

      $original_translation = $node->hasTranslation('fi') ? $node->getTranslation('fi') : $node;
      $languages = ['fi', 'en', 'sv'];
      foreach ($languages as $langcode) {
        $node = $node->hasTranslation($langcode) ? $node->getTranslation($langcode) : $original_translation;

        if ($node->hasField('field_dm_org_name')) {
          $data['organization'][$langcode] = $node->get('field_dm_org_name')->value;
        }
        if ($node->hasField('field_dm_org_above_name')) {
          $data['organization_above'][$langcode] = $node->get('field_dm_org_above_name')->value;
        }
      }

      $fields = $this->getFieldsHelper()
        ->filterForPropertyPath($item->getFields(),'entity:node','decisionmaker_searchfield_data');
      if (isset($fields['decisionmaker_searchfield_data'])) {
        $fields['decisionmaker_searchfield_data']->addValue(
          json_encode($data)
        );
      }

    }
  }

}