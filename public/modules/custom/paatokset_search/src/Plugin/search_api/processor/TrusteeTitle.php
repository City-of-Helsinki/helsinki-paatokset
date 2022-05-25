<?php

namespace Drupal\paatokset_search\Plugin\search_api\processor;

use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Drupal\paatokset_ahjo_api\Service\TrusteeService;

/**
 * Retrieves the trustee title with overrides.
 *
 * @SearchApiProcessor(
 *    id = "trustee_title",
 *    label = @Translation("Trustee title"),
 *    description = @Translation("Retrieves trustee titles"),
 *    stages = {
 *      "add_properties" = 0,
 *    },
 *    locked = true,
 *    hidden = true,
 * )
 */
class TrusteeTitle extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(DatasourceInterface $datasource = NULL) {
    $properties = [];

    if ($datasource) {
      $definition = [
        'label' => $this->t('Trustee title'),
        'description' => $this->t('Retrieves trustee titles'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['trustee_title'] = new ProcessorProperty($definition);
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

      if ($node->getType() !== 'trustee') {
        return;
      }

      $name = TrusteeService::getTrusteeTitle($node);
      $fields = $this->getFieldsHelper()->filterForPropertyPath($item->getFields(), 'entity:node', 'trustee_title');
      if ($name && isset($fields['trustee_title'])) {
        $fields['trustee_title']->addValue($name);
      }
    }
  }

}
