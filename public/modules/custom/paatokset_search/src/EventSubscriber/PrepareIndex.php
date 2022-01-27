<?php

namespace Drupal\paatokset_search\EventSubscriber;

use Drupal\elasticsearch_connector\Event\PrepareIndexEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * {@inheritdoc}
 */
class PrepareIndex implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      PrepareIndexEvent::PREPARE_INDEX => 'prepareIndices',
    ];
  }

  /**
   * Method to prepare index.
   *
   * @param Drupal\elasticsearch_connector\Event\PrepareIndexEvent $event
   *   The PrepareIndex event.
   */
  public function prepareIndices(PrepareIndexEvent $event) {
    $indexName = $event->getIndexName();
    $finnishIndices = [
      'paatokset_decisions',
      'paatokset_policymakers',
    ];
    if (in_array($indexName, $finnishIndices)) {
      $indexConfig = $event->getIndexConfig();
      $indexConfig['body']['settings']['analysis']['analyzer']['default']['type'] = 'finnish';
    }
  }

}
