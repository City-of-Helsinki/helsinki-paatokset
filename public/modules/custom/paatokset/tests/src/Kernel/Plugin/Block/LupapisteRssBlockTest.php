<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset\Kernel\Lupapiste;

use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\paatokset\Lupapiste\DTO\Item;
use Drupal\paatokset\Lupapiste\ItemsStorage;
use Drupal\paatokset\Plugin\Block\LupapisteRssBlock;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\helfi_api_base\Traits\LanguageManagerTrait;
use GuzzleHttp\Psr7\Response;

/**
 * Tests items storage.
 *
 * @coversDefaultClass \Drupal\paatokset\Plugin\Block\LupapisteRssBlock
 */
class LupapisteRssBlockTest extends KernelTestBase {

  use ApiTestTrait;
  use LanguageManagerTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_language_negotiator_test',
    'language',
    'helfi_api_base',
    'block',
    'serialization',
    'paatokset',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->setupLanguages();
    ConfigurableLanguage::createFromLangcode('it')->save();
  }

  /**
   * Tests block render.
   */
  public function testBlock(): void {
    $this->container->set('http_client', $this->setupMockHttpClient([
      // These must be returned in the same order as they are processed
      // in 'paatokset_update_lupapiste_items()' function.
      new Response(body: $this->getFixture('paatokset', 'rss_fi.xml')),
      new Response(body: $this->getFixture('paatokset', 'rss_en.xml')),
      new Response(body: $this->getFixture('paatokset', 'rss_sv.xml')),
      new Response(body: $this->getFixture('paatokset', 'rss_en.xml')),
    ]));
    paatokset_update_lupapiste_items();

    // Italy (it) language is not supported and should fall back to english.
    $languages = ['fi' => 'fi', 'en' => 'en', 'sv' => 'sv', 'it' => 'en'];

    foreach ($languages as $langcode => $expected) {
      $this->setOverrideLanguageCode($langcode);

      $block = LupapisteRssBlock::create($this->container, [], '', ['provider' => 'paatokset']);
      $build = $block->build();

      $this->assertArrayHasKey('#cache', $build);
      $this->assertArrayHasKey('contexts', $build['#cache']);
      $this->assertEquals(ItemsStorage::CACHE_TAGS, $build['#cache']['tags']);

      $this->assertCount(2, $build['items']);
      $this->assertInstanceOf(Item::class, $build['items'][0]['#item']);
      $this->assertEquals($expected . ' Asuinkerrostalon tai rivitalon rakentaminen', $build['items']['0']['#item']->title);
    }
  }

}
