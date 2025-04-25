<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset\Kernel\Lupapiste;

use Drupal\Core\Render\RendererInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
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
    'datetime',
    'block',
    'serialization',
    'system',
    'user',
    'paatokset',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->setupLanguages();
    $this->installConfig(['system']);
    ConfigurableLanguage::createFromLangcode('it')->save();
  }

  /**
   * Tests block render.
   */
  public function testBlock(): void {
    $this->container->set('http_client', $this->setupMockHttpClient([
      new Response(body: $this->getFixture('paatokset', 'rss_fi.xml')),
      new Response(body: $this->getFixture('paatokset', 'rss_en.xml')),
      new Response(body: $this->getFixture('paatokset', 'rss_sv.xml')),
    ]));
    // Italy (it) language is not supported and should fall back to english.
    $languages = ['fi' => 'fi', 'en' => 'en', 'sv' => 'sv', 'it' => 'en'];

    /** @var \Drupal\Core\Render\Renderer $renderer */
    $renderer = $this->container->get(RendererInterface::class);

    foreach ($languages as $langcode => $expected) {
      $this->setOverrideLanguageCode($langcode);

      $block = LupapisteRssBlock::create($this->container, [], '', ['provider' => 'paatokset']);
      $build = $block->build();
      $rendered = $renderer->renderInIsolation($build);
      $this->assertStringContainsString($expected . ' Rakennuslupa: Pientalo (tarvittaessa:) Aloittamisoikeus *****', (string) $rendered);
    }
  }

}
