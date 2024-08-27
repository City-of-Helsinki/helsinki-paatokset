<?php

declare(strict_types=1);

namespace Drupal\paatokset_search\Plugin\search_api\processor;

use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\paatokset_policymakers\Service\PolicymakerService;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Computes the meeting URL for fi, sv and en languages.
 *
 * @SearchApiProcessor(
 *    id = "meeting_url",
 *    label = @Translation("Meeting URL"),
 *    description = @Translation("Computes meeting URL for active languages."),
 *    stages = {
 *      "add_properties" = 0
 *    }
 * )
 */
class MeetingUrl extends ProcessorPluginBase {

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
  public function getPropertyDefinitions(DatasourceInterface $datasource = NULL) {
    $properties = [];

    if ($datasource) {
      $definition = [
        'label' => $this->t('Meeting URL'),
        'description' => $this->t('Computes meeting URL for active languages.'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['meeting_url'] = new ProcessorProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    $datasourceId = $item->getDatasourceId();
    if ($datasourceId === 'entity:node') {
      $node = $item->getOriginalObject()->getValue();

      if (!$node instanceof NodeInterface || $node->getType() !== 'meeting') {
        return;
      }

      if (!$node->hasField('field_meeting_id') || $node->get('field_meeting_id')->isEmpty() || !$node->hasField('field_meeting_dm_id') || $node->get('field_meeting_dm_id')->isEmpty()) {
        return;
      }

      $meeting_id = $node->get('field_meeting_id')->value;
      $dm_id = $node->get('field_meeting_dm_id')->value;

      $has_decision = FALSE;
      if ($node->hasField('field_meeting_decision') && !$node->get('field_meeting_decision')->isEmpty()) {
        $has_decision = TRUE;
      }

      $data = [
        'meeting_link' => [],
        'decision_link' => [],
      ];

      foreach (['fi', 'sv', 'en'] as $langcode) {
        $url = $this->policymakerService->getMinutesRoute($meeting_id, $dm_id, FALSE, $langcode);

        if ($url instanceof Url && !empty($url->toString())) {
          $data['meeting_link'][$langcode] = $url->toString();
        }

        $decision_url = NULL;
        if ($has_decision) {
          $decision_url = $this->policymakerService->getMinutesRoute($meeting_id, $dm_id, TRUE, $langcode);
        }
        if ($decision_url instanceof Url && !empty($decision_url->toString())) {
          $data['decision_link'][$langcode] = $decision_url->toString();
        }
      }

      $fields = $this->getFieldsHelper()
        ->filterForPropertyPath($item->getFields(), 'entity:node', 'meeting_url');

      if (isset($fields['meeting_url'])) {
        $fields['meeting_url']->addValue(json_encode($data));
      }
    }
  }

}
