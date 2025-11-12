<?php

declare(strict_types=1);

namespace Drupal\paatokset_search\Plugin\search_api\processor;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\paatokset_ahjo_api\Entity\Decision;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds a field to indicate if there are more decisions for the same case.
 *
 * @SearchApiProcessor(
 *   id = "more_decisions",
 *   label = @Translation("More decisions"),
 *   description = @Translation("Adds a field to indicate if there are more decisions for the same case."),
 *   stages = {
 *    "add_properties" = 0
 *   },
 *   locked = false,
 *   hidden = false,
 * )
 */
final class MoreDecisions extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    array $plugin_definition,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL): array {
    $properties = [];

    if ($datasource) {
      $definition = [
        'label' => $this->t('More decisions'),
        'description' => $this->t('Indicates if there are more decisions for the same case.'),
        'type' => 'boolean',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['more_decisions'] = new ProcessorProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item): void {
    $node = $item->getOriginalObject()->getValue();

    $more_decisions = FALSE;
    if ($node instanceof Decision) {
      $case = $node->getCase();
      if ($case && $this->getDecisionsCount($case->get('field_diary_number')->value) > 1) {
        $more_decisions = TRUE;
      }
    }

    $fields = $this
      ->getFieldsHelper()
      ->filterForPropertyPath($item->getFields(), $item->getDatasourceId(), 'more_decisions');
    foreach ($fields as $field) {
      $field->addValue($more_decisions);
    }
  }

  /**
   * Query decisions count for the case.
   */
  protected function getDecisionsCount(string $diary_number): int {
    return $this->entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->condition('type', 'decision')
      ->condition('field_diary_number', $diary_number)
      ->accessCheck(FALSE)
      ->count()
      ->execute();
  }

}
