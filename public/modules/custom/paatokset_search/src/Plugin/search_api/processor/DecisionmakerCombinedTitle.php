<?php

namespace Drupal\paatokset_search\Plugin\search_api\processor;

use Drupal\node\NodeInterface;
use Drupal\paatokset_ahjo_api\Service\TrusteeService;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;

/**
 * Combines decisionmaker title, sector and organization name into one field.
 *
 * @SearchApiProcessor(
 *    id = "decisionmaker_combined_title",
 *    label = @Translation("Decisionmaker combined title"),
 *    description = @Translation("Combine decisionmaker title, sector and org."),
 *    stages = {
 *      "add_properties" = 0,
 *    },
 *    locked = true,
 *    hidden = true,
 * )
 */
class DecisionmakerCombinedTitle extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(DatasourceInterface $datasource = NULL) {
    $properties = [];

    if ($datasource) {
      $definition = [
        'label' => $this->t('Decisionmaker combined title'),
        'description' => $this->t('Combine decisionmaker title, sector and org.'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['decisionmaker_combined_title'] = new ProcessorProperty($definition);
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

      $full_title = $node->title->value;
      if ($node->getType() === 'policymaker') {
        $sector_name = '';
        if ($node->hasField('field_sector_name') && !empty($node->get('field_sector_name'))) {
          $full_title .= ' - ' . $node->get('field_sector_name')->value;
          $sector_name = $node->get('field_sector_name')->value;
        }
        if ($node->hasField('field_dm_org_name') && !empty($node->get('field_dm_org_name')) && $sector_name != $node->get('field_dm_org_name')->value) {
          $full_title .= ' - ' . $node->get('field_dm_org_name')->value;
        }
      }

      if ($node->getType() === 'trustee') {
        $name = TrusteeService::getTrusteeName($node);
        if ($name) {
          $full_title = $name;
        }
      }

      $fields = $this->getFieldsHelper()->filterForPropertyPath($item->getFields(), 'entity:node', 'decisionmaker_combined_title');
      if (isset($fields['decisionmaker_combined_title'])) {
        $fields['decisionmaker_combined_title']->addValue($full_title);
      }
    }
  }

}