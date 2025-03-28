<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\search_api\processor;

use Drupal\node\NodeInterface;
use Drupal\paatokset_ahjo_api\Entity\Policymaker;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;

/**
 * Adds decisionmaker related data to index as json.
 *
 * @SearchApiProcessor(
 *    id = "decisionmaker_searchfield_data",
 *    label = @Translation("Decisionmaker Searchfield Data"),
 *    description = @Translation("Combines policymaker_id id, organization_name and organization_above_name."),
 *    stages = {
 *      "add_properties" = 0,
 *    }
 * )
 */
class DecisionmakerSearchfieldData extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL) {
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
    if ($datasourceId !== 'entity:node') {
      return;
    }

    $node = $item->getOriginalObject()->getValue();

    if (!$node instanceof Policymaker) {
      return;
    }

    $data = [
      'id' => $node->getPolicymakerId(),
    ];

    $original_translation = $node->hasTranslation('fi') ? $node->getTranslation('fi') : $node;
    $languages = ['fi', 'en', 'sv'];

    foreach ($languages as $langcode) {
      $node = $node->hasTranslation($langcode) ? $node->getTranslation($langcode) : $original_translation;

      if ($node->hasField('field_sector_name')) {
        $data['sector'][$langcode] = $node->get('field_sector_name')->value;
      }

      if ($node->hasField('field_ahjo_title')) {
        $data['organization'][$langcode] = $node->get('field_ahjo_title')->value;
      }
      if ($node->hasField('field_dm_org_name')) {
        $data['organization_above'][$langcode] = $node->get('field_dm_org_name')->value;
      }
    }

    $fields = $this->getFieldsHelper()
      ->filterForPropertyPath($item->getFields(), 'entity:node', 'decisionmaker_searchfield_data');

    foreach ($fields as $field) {
      $field->addValue(json_encode($data));
    }
  }

}
