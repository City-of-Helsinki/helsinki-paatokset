<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\ElasticSearch\Analyser;

use Drupal\elasticsearch_connector\Analyser\AnalyserBase;

/**
 * Finnish ngram analyser.
 *
 * @ElasticSearchAnalyser(
 *   id = "finnish_ngram",
 *   label = @Translation("Finnish ngram"),
 * )
 */
final class FinnishNgram extends AnalyserBase {

  /**
   * {@inheritdoc}
   */
  public function getSettings(): array {
    return [
      'analysis' => [
        'analyzer' => [
          'finnish_ngram' => [
            'tokenizer' => 'standard',
            'filter' => ['lowercase', 'finnish_stemmer', 'ngram_filter'],
          ],
          'finnish_search' => [
            'tokenizer' => 'standard',
            'filter' => ['lowercase', 'finnish_stemmer'],
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
    ];
  }

}
