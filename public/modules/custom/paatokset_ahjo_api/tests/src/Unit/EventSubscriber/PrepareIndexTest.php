<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Unit\EventSubscriber;

use Drupal\elasticsearch_connector\Event\AlterSettingsEvent;
use Drupal\paatokset_ahjo_api\EventSubscriber\PrepareIndex;
use Drupal\search_api\IndexInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests PrepareIndex event subscriber.
 */
#[Group('paatokset_ahjo_api')]
class PrepareIndexTest extends UnitTestCase {

  /**
   * Tests that Finnish analyzer is added to the decisions index.
   */
  public function testDecisionsIndexGetsFinnishAnalyzer(): void {
    $index = $this->createMock(IndexInterface::class);
    $index->method('id')->willReturn('decisions');

    $event = new AlterSettingsEvent([], [], $index);

    $subscriber = new PrepareIndex();
    $subscriber->prepareIndices($event);

    $settings = $event->getSettings();
    $this->assertEquals('finnish', $settings['index']['analysis']['analyzer']['default']['type']);
    $this->assertArrayHasKey('finnish_ngram', $settings['index']['analysis']['analyzer']);
    $this->assertArrayHasKey('finnish_search', $settings['index']['analysis']['analyzer']);
    $this->assertArrayHasKey('ngram_filter', $settings['index']['analysis']['filter']);
    $this->assertArrayHasKey('finnish_stemmer', $settings['index']['analysis']['filter']);
  }

  /**
   * Tests that Finnish analyzer is added to the policymakers index.
   */
  public function testPolicymakersIndexGetsFinnishAnalyzer(): void {
    $index = $this->createMock(IndexInterface::class);
    $index->method('id')->willReturn('policymakers');

    $event = new AlterSettingsEvent([], [], $index);

    $subscriber = new PrepareIndex();
    $subscriber->prepareIndices($event);

    $settings = $event->getSettings();
    $this->assertEquals('finnish', $settings['index']['analysis']['analyzer']['default']['type']);
    $this->assertArrayHasKey('finnish_ngram', $settings['index']['analysis']['analyzer']);
    $this->assertArrayHasKey('finnish_search', $settings['index']['analysis']['analyzer']);
    $this->assertArrayHasKey('ngram_filter', $settings['index']['analysis']['filter']);
    $this->assertArrayHasKey('finnish_stemmer', $settings['index']['analysis']['filter']);
  }

}
