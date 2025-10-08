<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\search_api\processor;

use Drupal\paatokset_ahjo_api\Entity\Meeting;
use Drupal\paatokset_ahjo_api\Entity\MeetingPhase;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;

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
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL): array {
    $properties = [];

    if ($datasource) {
      $properties['meeting_url'] = new ProcessorProperty([
        'label' => $this->t('Meeting URL'),
        'description' => $this->t('Computes meeting URL for active languages.'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      ]);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item): void {
    $datasourceId = $item->getDatasourceId();
    if ($datasourceId !== 'entity:node') {
      return;
    }

    $node = $item->getOriginalObject()->getValue();
    if (!$node instanceof Meeting) {
      return;
    }

    if ($node->get('field_meeting_id')->isEmpty() || $node->get('field_meeting_dm_id')->isEmpty()) {
      return;
    }

    $data = [
      'meeting_link' => [],
      'decision_link' => [],
    ];

    foreach (['fi', 'sv', 'en'] as $langcode) {
      // @todo check that these work correctly.
      $data['meeting_link'][$langcode] = $node->getMinutesUrl($langcode)->toString();

      if ($node->getMeetingPhase() === MeetingPhase::DECISION) {
        $data['decision_link'][$langcode] = $node->getDecisionAnnouncementUrl($langcode)->toString();
      }
    }

    $fields = $this->getFieldsHelper()
      ->filterForPropertyPath($item->getFields(), $item->getDatasourceId(), 'meeting_url');

    foreach ($fields as $field) {
      $field->addValue(json_encode($data));
    }
  }

}
