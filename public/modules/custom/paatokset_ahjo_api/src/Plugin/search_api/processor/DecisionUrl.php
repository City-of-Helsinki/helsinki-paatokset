<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\search_api\processor;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\paatokset_ahjo_api\Entity\Decision;
use Drupal\paatokset_ahjo_api\Service\CaseService;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Computes the decision URL for the indexed decision.
 */
#[SearchApiProcessor(
  id: 'decision_url',
  label: new TranslatableMarkup('Decision URL'),
  description: new TranslatableMarkup('Computes decision URL for the document'),
  stages: [
    'add_properties' => 0
  ],
)]
class DecisionUrl extends ProcessorPluginBase {

  private const string PROPERTY_PATH = 'decision_url';

  /**
   * Case service.
   */
  protected CaseService $caseService;

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<mixed> $configuration
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->caseService = $container->get(CaseService::class);

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL): array {
    $properties = [];

    if ($datasource) {
      $definition = [
        'label' => $this->t('Decision URL'),
        'description' => $this->t('Computed URL value for the decision'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      ];
      $properties[self::PROPERTY_PATH] = new ProcessorProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param \Drupal\search_api\Item\ItemInterface<mixed> $item
   */
  public function addFieldValues(ItemInterface $item): void {
    $datasourceId = $item->getDatasourceId();
    if ($datasourceId !== 'entity:node') {
      return;
    }

    $node = $item->getOriginalObject()->getValue();
    if (!$node instanceof Decision) {
      return;
    }

    $decision = $node;

    $language = $decision->language();
    $decisionUrl = $this->caseService->getDecisionUrlFromNode($decision, $language->getId());

    $fields = $this->getFieldsHelper()
      ->filterForPropertyPath($item->getFields(), $item->getDatasourceId(), self::PROPERTY_PATH);

    if ($decisionUrl instanceof Url) {
      foreach ($fields as $field) {
        $field->addValue($decisionUrl->toString());
      }
    }
  }

}
