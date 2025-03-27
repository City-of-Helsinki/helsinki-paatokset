<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\search_api\processor;

use Drupal\node\NodeInterface;
use Drupal\paatokset_ahjo_api\Entity\Decision;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Extracts sector info from source JSON.
 *
 * @SearchApiProcessor(
 *   id = "sector_id",
 *   label = @Translation("Sector ID"),
 *   description = @Translation("Adds sector ID from JSON object"),
 *   stages = {
 *     "add_properties" = 0,
 *   },
 *   locked = true,
 *   hidden = true,
 * )
 */
class SectorId extends ProcessorPluginBase {

  /**
   * Field with sector JSON.
   */
  const SECTOR_FIELD = 'field_dm_sector';

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL) {
    $properties = [];

    if ($datasource) {
      $definition = [
        'label' => $this->t('Sector ID'),
        'description' => $this->t('Sector ID from JSON object'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['sector_id'] = new ProcessorProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    $datasourceId = $item->getDatasourceId();
    if ($datasourceId !== 'entity:node') {
      return;
    }

    $node = $item->getOriginalObject()->getValue();

    $policymaker = NULL;
    if ($node->getType() === 'policymaker') {
      $policymaker = $node;
    }
    elseif ($node instanceof Decision) {
      $policymaker = $node->getPolicymaker($item->getLanguage());
    }

    if (!$policymaker instanceof NodeInterface) {
      return;
    }

    if (!$policymaker->hasField(self::SECTOR_FIELD) || $policymaker->get(self::SECTOR_FIELD)->isEmpty()) {
      return;
    }

    $sectorData = $policymaker->get(self::SECTOR_FIELD)->value;
    if ($sectorData === 'null') {
      return;
    }

    $json_data = json_decode($sectorData);
    if (!isset($json_data->SectorID)) {
      return;
    }

    $fields = $this->getFieldsHelper()
      ->filterForPropertyPath($item->getFields(), 'entity:node', 'sector_id');

    foreach ($fields as $field) {
      $field->addValue($json_data->SectorID);
    }
  }

}
