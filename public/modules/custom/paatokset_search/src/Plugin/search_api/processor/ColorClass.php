<?php

namespace Drupal\paatokset_search\Plugin\search_api\processor;

use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;

/**
 * Computes CSS class for the given entity.
 *
 * @SearchApiProcessor(
 *    id = "color_class",
 *    label = @Translation("Color class"),
 *    description = @Translation("Computes CSS class for entity"),
 *    stages = {
 *      "add_properties" = 0
 *    },
 *    locked = true,
 *    hidden = true
 * )
 */
class ColorClass extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(DataSourceInterface $datasource = NULL) {
    $properties = [];

    if ($datasource) {
      $definition = [
        'label' => $this->t('Color Class'),
        'description' => $this->t('Computes CSS class for entity'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['color_class'] = new ProcessorProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    $datasourceId = $item->getDataSourceId();
    if ($datasourceId === 'entity:node' && $node = $item->getOriginalObject()->getValue()) {
      $decisionMakers = [
        'trustee',
        'policymaker',
      ];
      $type = $node->getType();
      /** @var \Drupal\paatokset_policymakers\Service\PolicymakerService */
      $policymakerService = \Drupal::service('paatokset_policymakers');
      $colorClass = NULL;
      if ($type === 'decision' && $id = $node->get('field_policymaker_id')->value) {
        $colorClass = $policymakerService->getPolicymakerClassById($id);
      }
      elseif (in_array($type, $decisionMakers)) {
        $colorClass = $policymakerService->getPolicymakerClass($node);
      }

      $fields = $this->getFieldsHelper()->filterForPropertyPath($item->getFields(), 'entity:node', 'color_class');
      if (isset($fields['color_class'])) {
        $fields['color_class']->addValue($colorClass);
      }
    }
  }

}
