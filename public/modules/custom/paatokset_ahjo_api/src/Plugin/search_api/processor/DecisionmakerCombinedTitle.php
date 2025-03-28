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
 * Combines decisionmaker title, sector and organization name into one field.
 *
 * @SearchApiProcessor(
 *    id = "decisionmaker_combined_title",
 *    label = @Translation("Decisionmaker combined title"),
 *    description = @Translation("Combine decisionmaker title, sector and org."),
 *    stages = {
 *      "add_properties" = 0,
 *    },
 *    locked = true,
 *    hidden = true,
 * )
 */
class DecisionmakerCombinedTitle extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL): array {
    $properties = [];

    if ($datasource) {
      $definition = [
        'label' => $this->t('Decisionmaker combined title'),
        'description' => $this->t('Combine decisionmaker title, sector and org.'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['decisionmaker_combined_title'] = new ProcessorProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item): void {
    $datasourceId = $item->getDataSourceId();
    if ($datasourceId !== 'entity:node') {
      return;
    }

    $node = $item->getOriginalObject()->getValue();

    if (!$node instanceof NodeInterface) {
      return;
    }

    $full_title = $node->get('title')->value;
    if ($node->getType() === 'policymaker') {
      $title_sections = [$full_title];

      if ($node->hasField('field_sector_name') && !$node->get('field_sector_name')->isEmpty()) {
        $title_sections[] = $node->get('field_sector_name')->value;
      }
      if ($node->hasField('field_dm_org_name') && !$node->get('field_dm_org_name')->isEmpty()) {
        $title_sections[] = $node->get('field_dm_org_name')->value;
      }

      $title_sections = array_unique($title_sections);
      $full_title = implode(" - ", $title_sections);
    }

    if ($node->getType() === 'trustee') {
      $name = TrusteeService::getTrusteeName($node);
      if ($name) {
        $full_title = $name;
      }
    }

    $fields = $this
      ->getFieldsHelper()
      ->filterForPropertyPath($item->getFields(), $item->getDatasourceId(), 'decisionmaker_combined_title');

    foreach ($fields as $field) {
      $field->addValue($full_title);
    }
  }

}
