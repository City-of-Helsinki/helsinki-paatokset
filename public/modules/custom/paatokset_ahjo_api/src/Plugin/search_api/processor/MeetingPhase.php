<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\search_api\processor;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\node\NodeInterface;
use Drupal\paatokset_ahjo_api\Entity\Meeting;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;

/**
 * Adds meeting phase to index (agenda, decision or minutes published).
 *
 * @SearchApiProcessor(
 *    id = "meeting_phase",
 *    label = @Translation("Meeting phase."),
 *    description = @Translation("Adds meeting phase to index (agenda, decision or minutes published)."),
 *    stages = {
 *      "add_properties" = 0,
 *    }
 * )
 */
class MeetingPhase extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL) {
    $properties = [];

    if ($datasource) {
      $definition = [
        'label' => $this->t('Meeting phase'),
        'description' => $this->t('Adds meeting phase to index (agenda, decision or minutes published).'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['meeting_phase'] = new ProcessorProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item): void {
    $datasourceId = $item->getDataSourceId();
    if ($datasourceId === 'entity:node') {
      $node = $item->getOriginalObject()->getValue();
      if (!$node instanceof Meeting) {
        return;
      }

      $phase = $node->getMeetingPhase();

      $fields = $this->getFieldsHelper()
        ->filterForPropertyPath($item->getFields(), $item->getDatasourceId(), 'meeting_phase');

      foreach ($fields as $field) {
        $field->addValue($phase?->value);
      }
    }
  }

}
