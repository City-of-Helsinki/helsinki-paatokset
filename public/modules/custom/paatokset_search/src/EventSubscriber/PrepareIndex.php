<?php

declare(strict_types=1);

namespace Drupal\paatokset_search\EventSubscriber;

use Drupal\Component\Utility\NestedArray;
use Drupal\elasticsearch_connector\Event\AlterSettingsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Elasticsearch event subscriber.
 */
class PrepareIndex implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      AlterSettingsEvent::class => 'prepareIndices',
    ];
  }

  /**
   * Method to prepare index.
   *
   * @param \Drupal\elasticsearch_connector\Event\AlterSettingsEvent $event
   *   The PrepareIndex event.
   */
  public function prepareIndices(AlterSettingsEvent $event): void {
    $indexName = $event->getIndex()->id();
    $finnishIndices = [
      'paatokset_decisions',
      'paatokset_policymakers',
    ];
    if (in_array($indexName, $finnishIndices)) {
      $event->setSettings(NestedArray::mergeDeep(
        $event->getSettings(),
        [
          'analysis' => [
            'analyzer' => [
              'default' => [
                'type' => 'finnish',
              ],
            ],
          ],
        ],
      ));
    }
  }

}
