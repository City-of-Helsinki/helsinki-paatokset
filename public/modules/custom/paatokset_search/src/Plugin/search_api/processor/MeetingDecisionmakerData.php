<?php

declare(strict_types=1);

namespace Drupal\paatokset_search\Plugin\search_api\processor;

use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\node\NodeInterface;
use Drupal\paatokset_ahjo_api\Entity\Policymaker;
use Drupal\paatokset_policymakers\Service\PolicymakerService;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds decisionmaker related data to meetings index as json.
 *
 * @SearchApiProcessor(
 *    id = "meeting_dm_data",
 *    label = @Translation("Meeting Decisionmaker Data"),
 *    description = @Translation("Combines translated decisionmaker title and type as data."),
 *    stages = {
 *      "add_properties" = 0,
 *    }
 * )
 */
class MeetingDecisionmakerData extends ProcessorPluginBase {

  /**
   * PolicymakerService.
   *
   * @var \Drupal\paatokset_policymakers\Service\PolicymakerService
   */
  private PolicymakerService $policymakerService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $instance */
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->policymakerService = $container->get('paatokset_policymakers');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL) {
    $properties = [];

    if ($datasource) {
      $definition = [
        'label' => $this->t('Meeting Decisionmaker Data'),
        'description' => $this->t('Combines translated decisionmaker title and type as data.'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['meeting_dm_data'] = new ProcessorProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    $datasourceId = $item->getDataSourceId();
    if ($datasourceId === 'entity:node') {
      $node = $item->getOriginalObject()->getValue();

      if (!$node instanceof NodeInterface || $node->getType() !== 'meeting') {
        return;
      }

      if (!$node->hasField('field_meeting_dm_id') || $node->get('field_meeting_dm_id')->isEmpty()) {
        return;
      }

      $dm_id = $node->get('field_meeting_dm_id')->value;
      $dm_node = $this->policymakerService->getPolicyMaker($dm_id);

      if (!$dm_node instanceof Policymaker) {
        return;
      }

      $data = [
        'title' => [],
        'type' => '',
      ];

      if ($dm_node->hasField('field_organization_type') && !$dm_node->get('field_organization_type')->isEmpty()) {
        $data['type'] = strtolower($dm_node->get('field_organization_type')->value);
      }

      foreach (['fi', 'sv', 'en'] as $langcode) {
        if ($dm_node->hasTranslation($langcode)) {
          $translation = $dm_node->getTranslation($langcode);
          assert($translation instanceof Policymaker);
          $data['title'][$langcode] = $translation->getPolicymakerName();
        }
      }

      $fields = $this->getFieldsHelper()
        ->filterForPropertyPath($item->getFields(), 'entity:node', 'meeting_dm_data');

      if (isset($fields['meeting_dm_data'])) {
        $fields['meeting_dm_data']->addValue(json_encode($data));
      }

    }
  }

}
