<?php

declare(strict_types=1);

namespace Drupal\paatokset_search\Plugin\search_api\processor;

use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;

/**
 * Computes CSS class for the given entity.
 *
 * @SearchApiProcessor(
 *    id = "special_status",
 *    label = @Translation("Special status"),
 *    description = @Translation("Marks the entity with pre-defined special statuses"),
 *    stages = {
 *      "add_properties" = 0
 *    },
 *    locked = true,
 *    hidden = true
 * )
 */
class SpecialStatus extends ProcessorPluginBase {
  const CITY_COUNCIL = '_city_council';
  const CITY_HALL = '_city_hall';
  const TRUSTEE = '_trustee';

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(DataSourceInterface $datasource = NULL) {
    $properties = [];

    if ($datasource) {
      $definition = [
        'label' => $this->t('Special status'),
        'description' => $this->t('Marks the entity with pre-defined special statuses'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['special_status'] = new ProcessorProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    $datasourceId = $item->getDataSourceId();
    if ($datasourceId === 'entity:node' && $node = $item->getOriginalObject()->getValue()) {
      if ($node->getType() === 'decision') {
        $status = NULL;
        $id = $node->get('field_policymaker_id')->value;
        if ((string) \Drupal::config('paatokset_helsinki_kanava.settings')->get('city_council_id') === $id) {
          $status = self::CITY_COUNCIL;
        }
        elseif ((string) \Drupal::config('paatokset_helsinki_kanava.settings')->get('city_hall_id') === $id) {
          $status = self::CITY_HALL;
        }
        else {
          /** @var \Drupal\paatokset_policymakers\Service\PolicymakerService */
          $policymakerService = \Drupal::service('paatokset_policymakers');
          $policymaker = $policymakerService->getPolicymaker($id);
          if ($policymaker && (string) $policymaker->get('field_organization_type_id')->value === \Drupal::config('paatokset_helsinki_kanava.settings')->get('trustee_organization_type_id')) {
            $status = self::TRUSTEE;
          }
        }

        if ($status) {
          $fields = $this->getFieldsHelper()->filterForPropertyPath($item->getFields(), 'entity:node', 'special_status');
          if (isset($fields['special_status'])) {
            $fields['special_status']->addValue($status);
          }
        }
      }
    }
  }

}
