<?php

declare(strict_types=1);

namespace Drupal\paatokset_search\Plugin\search_api\processor;

use Drupal\node\NodeInterface;
use Drupal\paatokset_ahjo_api\Service\CaseService;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Checks if node has translations, with special cases for decisions.
 *
 * @SearchApiProcessor(
 *    id = "has_translation",
 *    label = @Translation("Has translation"),
 *    description = @Translation("Checks if node has translations"),
 *    stages = {
 *      "add_properties" = 0
 *    },
 *    locked = true,
 *    hidden = true
 * )
 */
class HasTranslation extends ProcessorPluginBase {

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
    /** @var static $processor */
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->caseService = $container->get('paatokset_ahjo_cases');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(DataSourceInterface $datasource = NULL) {
    $properties = [];

    if ($datasource) {
      $definition = [
        'label' => $this->t('Has Translation'),
        'description' => $this->t('Checks if node has translations'),
        'type' => 'boolean',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['has_translation'] = new ProcessorProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    $datasourceId = $item->getDataSourceId();

    // Only act on nodes for now.
    if ($datasourceId !== 'entity:node') {
      return;
    }
    $node = $item->getOriginalObject()->getValue();
    if (!$node instanceof NodeInterface) {
      return;
    }

    $hasTranslation = $this->checkNodeTranslation($node);

    $fields = $this->getFieldsHelper()->filterForPropertyPath($item->getFields(), 'entity:node', 'has_translation');
    if (isset($fields['has_translation'])) {
      $fields['has_translation']->addValue($hasTranslation);
    }
  }

  /**
   * Check if node has translations. Special cases for decisions.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Node to check translations for.
   *
   * @return bool
   *   True if node has translations.
   */
  private function checkNodeTranslation(NodeInterface $node): bool {
    $translations = $node->getTranslationLanguages(FALSE);
    if (count($translations) >= 1) {
      return TRUE;
    }

    // Decisions don't use built-in translations.
    if ($node->getType() === 'decision') {
      return $this->caseService->decisionHasTranslations($node);
    }

    return FALSE;
  }

}
