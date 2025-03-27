<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\search_api\processor;

use Drupal\node\NodeInterface;
use Drupal\paatokset_ahjo_api\Service\TrusteeService;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;

/**
 * Transforms trustee title into conventional name spelling.
 *
 * @SearchApiProcessor(
 *    id = "trustee_name",
 *    label = @Translation("Trustee name"),
 *    description = @Translation("Transform trustee title into name"),
 *    stages = {
 *      "add_properties" = 0,
 *    },
 *    locked = true,
 *    hidden = true,
 * )
 */
class TrusteeName extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL) {
    $properties = [];

    if ($datasource) {
      $definition = [
        'label' => $this->t('Trustee name'),
        'description' => $this->t('Convert trustee title into name'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['trustee_name'] = new ProcessorProperty($definition);
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

      if ($node instanceof NodeInterface && $node->getType() !== 'trustee') {
        return;
      }

      $name = TrusteeService::getTrusteeName($node);
      $fields = $this->getFieldsHelper()->filterForPropertyPath($item->getFields(), 'entity:node', 'trustee_name');
      if (isset($fields['trustee_name'])) {
        $fields['trustee_name']->addValue($name);
      }
    }
  }

}
