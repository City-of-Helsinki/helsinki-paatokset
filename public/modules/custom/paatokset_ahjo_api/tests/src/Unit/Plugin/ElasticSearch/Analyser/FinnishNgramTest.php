<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Unit\Plugin\ElasticSearch\Analyser;

use Drupal\paatokset_ahjo_api\Plugin\ElasticSearch\Analyser\FinnishNgram;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the FinnishNgram analyser plugin.
 */
#[Group('paatokset_ahjo_api')]
class FinnishNgramTest extends UnitTestCase {

  /**
   * Tests that the plugin provides the finnish_ngram analysis settings.
   */
  public function testGetSettings(): void {
    $plugin = new FinnishNgram([], 'finnish_ngram', []);

    $analysis = $plugin->getSettings()['analysis'];
    $this->assertArrayHasKey('finnish_ngram', $analysis['analyzer']);
    $this->assertArrayHasKey('finnish_search', $analysis['analyzer']);
    $this->assertArrayHasKey('ngram_filter', $analysis['filter']);
    $this->assertArrayHasKey('finnish_stemmer', $analysis['filter']);
  }

}
