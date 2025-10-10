<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\search_api\processor;

use Drupal\paatokset_ahjo_api\Entity\Meeting;
use Drupal\paatokset_ahjo_api\Entity\MeetingPhase;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Psr\Log\LoggerInterface;
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
final class MeetingUrl extends ProcessorPluginBase {

  /**
   * Logger interface.
   */
  private LoggerInterface $logger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $processor = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $processor->logger = $container->get('logger.channel.paatokset_ahjo_api');
    return $processor;
  }

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

    // Maybe this should not index the field at all if this meeting does
    // not support minutes URL. However, that would require testing that
    // the frontend still works.
    foreach (['fi', 'sv', 'en'] as $langcode) {
      if ($minutesUrl = $node->getMinutesUrl($langcode)?->toString()) {
        $data['meeting_link'][$langcode] = $minutesUrl;
      }

      if ($node->getMeetingPhase() === MeetingPhase::DECISION && $decisionUrl = $node->getDecisionAnnouncementUrl($langcode)?->toString()) {
        $data['decision_link'][$langcode] = $decisionUrl;
      }
    }

    $fields = $this->getFieldsHelper()
      ->filterForPropertyPath($item->getFields(), $item->getDatasourceId(), 'meeting_url');

    foreach ($fields as $field) {
      $field->addValue(json_encode($data));
    }
  }

}
