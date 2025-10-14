<?php

declare(strict_types=1);

namespace Drupal\paatokset_search\EventSubscriber;

use Drupal\Component\Utility\NestedArray;
use Drupal\elasticsearch_connector\Event\AlterSettingsEvent;
use Drupal\elasticsearch_connector\Event\FieldMappingEvent;
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
      FieldMappingEvent::class => 'onFieldMapping',
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
      'decisions',
      'policymakers',
    ];
    if (in_array($indexName, $finnishIndices)) {
      $event->setSettings(NestedArray::mergeDeep(
        $event->getSettings(),
        [
          'index' => [
            'analysis' => [
              'analyzer' => [
                'default' => [
                  'type' => 'finnish',
                ],
                'finnish_ngram' => [
                  'tokenizer' => 'standard',
                  'filter' => [
                    'lowercase',
                    'finnish_stemmer',
                    'ngram_filter',
                  ],
                ],
                'finnish_search' => [
                  'tokenizer' => 'standard',
                  'filter' => [
                    'lowercase',
                    'finnish_stemmer',
                  ],
                ],
              ],
              'filter' => [
                'ngram_filter' => [
                  'type' => 'edge_ngram',
                  'min_gram' => 3,
                  'max_gram' => 15,
                ],
                'finnish_stemmer' => [
                  'type' => 'stemmer',
                  'language' => 'finnish',
                ],
              ],
            ],
          ],
        ],
      ));
    }
  }

  /**
   * Alters individual field mappings.
   *
   * @param \Drupal\elasticsearch_connector\Event\FieldMappingEvent $event
   *   The field mapping event.
   */
  public function onFieldMapping(FieldMappingEvent $event): void {
    $field = $event->getField();

    // Modify the 'subject' field to use custom analyzer.
    if ($field->getFieldIdentifier() === 'subject') {
      $param = $event->getParam();

      $param['analyzer'] = 'finnish_ngram';
      $param['search_analyzer'] = 'finnish_search';

      // Add multi-field for keyword searches.
      $param['fields'] = [
        'keyword' => [
          'type' => 'keyword',
          'ignore_above' => 256,
        ],
      ];

      $event->setParam($param);
    }
  }

}
