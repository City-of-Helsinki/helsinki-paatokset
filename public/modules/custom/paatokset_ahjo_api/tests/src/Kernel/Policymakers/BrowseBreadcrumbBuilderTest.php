<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\Policymakers;

use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\language\ConfigurableLanguageManagerInterface;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\paatokset_ahjo_api\Policymakers\BrowseBreadcrumbBuilder;
use Drupal\Tests\paatokset_ahjo_api\Kernel\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Routing\Route;

/**
 * Tests BrowseBreadcrumbBuilder.
 */
#[Group('paatokset_ahjo_api')]
class BrowseBreadcrumbBuilderTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'path_alias',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Install path alias schema required by Url::fromUserInput().
    $this->installEntitySchema('path_alias');

    // Install language configuration.
    $this->installConfig(['language']);

    ConfigurableLanguage::createFromLangcode('fi')->save();
    ConfigurableLanguage::createFromLangcode('sv')->save();
  }

  /**
   * Returns the breadcrumb builder service.
   */
  private function builder(): BrowseBreadcrumbBuilder {
    return $this->container->get(BrowseBreadcrumbBuilder::class);
  }

  /**
   * Creates a route match object for testing.
   */
  private function routeMatch(string $routeName, array $params = []): RouteMatch {
    $placeholders = implode('/', array_map(fn ($k) => "{{$k}}", array_keys($params)));
    $path = $placeholders ? "/test/$placeholders" : '/test';
    return new RouteMatch($routeName, new Route($path), $params);
  }

  /**
   * Tests that the builder only applies to the browse policymakers route.
   */
  public function testApplies(): void {
    $builder = $this->builder();

    $this->assertTrue($builder->applies($this->routeMatch('paatokset_ahjo_api.browse_policymakers')));
    $this->assertFalse($builder->applies($this->routeMatch('<front>')));
    $this->assertFalse($builder->applies($this->routeMatch('entity.node.canonical')));
  }

  /**
   * Tests breadcrumb structure.
   */
  public function testBuild(): void {
    $languageManager = $this->container->get(LanguageManagerInterface::class);
    $this->assertInstanceOf(ConfigurableLanguageManagerInterface::class, $languageManager);

    foreach (['fi', 'sv', 'en'] as $langcode) {
      $languageManager->setCurrentLanguage(ConfigurableLanguage::load($langcode));

      $breadcrumb = $this->builder()->build(
        $this->routeMatch('paatokset_ahjo_api.browse_policymakers')
      );

      $links = array_values($breadcrumb->getLinks());

      $this->assertCount(3, $links);

      $this->assertSame('Home', (string) $links[0]->getText());
      $this->assertSame('Decision-making', (string) $links[1]->getText());
      $this->assertSame('Browse decision-makers', (string) $links[2]->getText());

      $this->assertSame(
        'paatokset_ahjo_api.browse_policymakers',
        $links[2]->getUrl()->getRouteName()
      );
    }
  }

  /**
   * Tests that the required cache contexts are declared.
   */
  public function testCacheContexts(): void {
    $breadcrumb = $this->builder()->build(
      $this->routeMatch('paatokset_ahjo_api.browse_policymakers')
    );

    $contexts = $breadcrumb->getCacheContexts();

    $this->assertContains('languages:language_interface', $contexts);
    $this->assertContains('url.path', $contexts);
  }

}