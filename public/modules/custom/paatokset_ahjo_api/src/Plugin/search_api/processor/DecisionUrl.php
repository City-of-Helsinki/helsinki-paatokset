<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\search_api\processor;

use Drupal\Core\Url;
use Drupal\paatokset_ahjo_api\Service\CaseService;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * Case service.
   *
   * @var \Drupal\paatokset_ahjo_api\Service\CaseService
   */
  private CaseService $caseService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->caseService = $container->get('paatokset_ahjo_cases');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL) {
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
      $language = $decision->language();
      $decisionUrl = $this->caseService->getDecisionUrlFromNode($decision, $language->getId());
      $fields = $this->getFieldsHelper()->filterForPropertyPath($item->getFields(), 'entity:node', 'decision_url');
      if ($decisionUrl instanceof Url && isset($fields['decision_url'])) {
        $fields['decision_url']->addValue($decisionUrl->toString());
      }
    }
  }

}
