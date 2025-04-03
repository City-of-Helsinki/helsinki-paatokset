<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\search_api\processor;

use Drupal\paatokset_ahjo_api\Entity\Decision;
use Drupal\paatokset_ahjo_api\Entity\Policymaker;
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
  public function getPropertyDefinitions(?DataSourceInterface $datasource = NULL): array {
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
  public function addFieldValues(ItemInterface $item): void {
    $node = $item->getOriginalObject()->getValue();

    $colorClass = NULL;
    if ($node instanceof Decision) {
      $colorClass = $node->getPolicymaker($node->language()->getId())?->getPolicymakerClass();
    }
    elseif ($node instanceof Policymaker) {
      $colorClass = $node->getPolicymakerClass();
    }

    $fields = $this
      ->getFieldsHelper()
      ->filterForPropertyPath($item->getFields(), $item->getDatasourceId(), 'color_class');

    foreach ($fields as $field) {
      $field->addValue($colorClass ?? 'color-none');
    }
  }

}
