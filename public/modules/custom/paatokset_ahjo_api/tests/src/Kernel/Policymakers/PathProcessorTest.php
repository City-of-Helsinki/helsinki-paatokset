<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Kernel\Policymakers;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;
use Drupal\language\ConfigurableLanguageManagerInterface;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\paatokset_ahjo_api\Policymakers\PathProcessor;
use Drupal\Tests\paatokset_ahjo_api\Kernel\KernelTestBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests policymaker path processor.
 */
class PathProcessorTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('path_alias');

    // Install language configuration.
    $this->installConfig(['language']);

    ConfigurableLanguage::createFromLangcode('fi')->save();
    ConfigurableLanguage::createFromLangcode('sv')->save();
  }

  /**
   * Tests inbound path processor.
   */
  public function testInboundPathProcessor(): void {
    $languageManager = $this->container->get(LanguageManagerInterface::class);
    $this->assertInstanceOf(ConfigurableLanguageManagerInterface::class, $languageManager);

    $sut = new PathProcessor($languageManager);

    $tests = [
      'fi' => [
        '/decisionmakers/browse-decisionmakers' => '/paattajat/selaa-paattajia',
        '/decisionmakers/browse-decisionmakers/kaupunginvaltuusto' => '/paattajat/selaa-paattajia/kaupunginvaltuusto',
      ],
      'sv' => [
        '/decisionmakers/browse-decisionmakers' => '/beslutsfattare/bladra-bland-beslutsfattare',
        '/decisionmakers/browse-decisionmakers/stadsfullmäktige' => '/beslutsfattare/bladra-bland-beslutsfattare/stadsfullmäktige',
      ],
      'en' => [
        '/decisionmakers/browse-decisionmakers' => '/decisionmakers/browse-decisionmakers',
        '/decisionmakers/browse-decisionmakers/city-council' => '/decisionmakers/browse-decisionmakers/city-council',
      ],
    ];

    foreach ($tests as $langcode => $paths) {
      $languageManager->setCurrentLanguage(ConfigurableLanguage::load($langcode));

      foreach ($paths as $expected => $actual) {
        $this->assertEquals($expected, $sut->processInbound($actual, $this->createMock(Request::class)));
      }
    }
  }

  /**
   * Tests outbound path processor.
   */
  public function testOutboundPathProcessor(): void {
    $languageManager = $this->container->get(LanguageManagerInterface::class);
    $this->assertInstanceOf(ConfigurableLanguageManagerInterface::class, $languageManager);

    $tests = [
      'fi' => [
        '/paattajat/selaa-paattajia' => [],
        '/paattajat/selaa-paattajia/kaupunginvaltuusto' => ['org' => 'kaupunginvaltuusto'],
      ],
      'sv' => [
        '/beslutsfattare/bladra-bland-beslutsfattare' => [],
        '/beslutsfattare/bladra-bland-beslutsfattare/stadsfullmäktige' => ['org' => 'stadsfullmäktige'],
      ],
      'en' => [
        '/decisionmakers/browse-decisionmakers' => [],
        '/decisionmakers/browse-decisionmakers/city-council' => ['org' => 'city-council'],
      ],
    ];

    foreach ($tests as $langcode => $paths) {
      foreach ($paths as $path => $parameters) {
        $url = Url::fromRoute('paatokset_ahjo_api.browse_policymakers', $parameters, [
          'language' => $languageManager->getLanguage($langcode),
        ]);

        $this->assertEquals($path, urldecode($url->toString()));
      }
    }

    // Path processor should work without a language parameter:
    $languageManager->setCurrentLanguage(ConfigurableLanguage::load('fi'));
    $url = Url::fromRoute('paatokset_ahjo_api.browse_policymakers');
    $this->assertEquals('/paattajat/selaa-paattajia', urldecode($url->toString()));
  }

}
