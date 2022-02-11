<?php

namespace Drupal\paatokset_search\Plugin\search_api\processor;

use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;

/**
 * Computes the decision URL for the indexed decision.
 *
 * @SearchApiProcessor(
 *    id = "decision_url",
 *    label = @Translation("Decision URL"),
 *    description = @Translation("Computes decision URL for the document"),
 *    stages = {
 *      "add_properties" = 0
 *    },
 *    locked = true,
 *    hidden = true
 * )
 */
class DecisionUrl extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(DatasourceInterface $datasource = NULL) {
    $properties = [];

    if ($datasource) {
      $definition = [
        'label' => $this->t('Decision URL'),
        'description' => $this->t('Computed URL value for the decision'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['decision_url'] = new ProcessorProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    $datasourceId = $item->getDatasourceId();
    if ($datasourceId === 'entity:node' && $decision = $item->getOriginalObject()->getValue()) {
      /** @var \Drupal\paatokset_ahjo_api\Service\CaseService */
      $caseService = \Drupal::service('paatokset_ahjo_cases');
      $decisionUrl = $caseService->getDecisionUrlFromNode($decision);
      $fields = $this->getFieldsHelper()->filterForPropertyPath($item->getFields(), 'entity:node', 'decision_url');
      if (isset($fields['decision_url'])) {
        $fields['decision_url']->addValue($decisionUrl->toString());
      }
    }
  }

}
