<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\Policymakers;

use Drupal\Core\Routing\RouteMatch;
use Drupal\paatokset_ahjo_api\Policymakers\BrowseBreadcrumbBuilder;
use Drupal\Tests\paatokset_ahjo_api\Kernel\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Routing\Route;

/**
 * Tests BrowseBreadcrumbBuilder.
 */
#[Group('paatokset_ahjo_api')]
class BrowseBreadcrumbBuilderTest extends KernelTestBase {

  private function builder(): BrowseBreadcrumbBuilder {
    return $this->container->get(BrowseBreadcrumbBuilder::class);
  }

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
   * Tests breadcrumb at root level (no org parameter).
   *
   * Root shows only Home — the page title already reads "Browse decisionmakers".
   */
  public function testBuildRoot(): void {
    $breadcrumb = $this->builder()->build($this->routeMatch('paatokset_ahjo_api.browse_policymakers'));
    $links = $breadcrumb->getLinks();

    $this->assertCount(1, $links);
    $this->assertSame('Home', (string) reset($links)->getText());
  }

  /**
   * Tests breadcrumb when an org parameter is present.
   *
   * Org page adds a "Browse decisionmakers" crumb linking back to root.
   */
  public function testBuildWithOrg(): void {
    $breadcrumb = $this->builder()->build(
      $this->routeMatch('paatokset_ahjo_api.browse_policymakers', ['org' => 'some-org'])
    );
    $links = array_values($breadcrumb->getLinks());

    $this->assertCount(2, $links);
    $this->assertSame('Home', (string) $links[0]->getText());
    $this->assertSame('Browse decisionmakers', (string) $links[1]->getText());
    $this->assertSame('paatokset_ahjo_api.browse_policymakers', $links[1]->getUrl()->getRouteName());
  }

  /**
   * Tests that the required cache contexts are declared.
   */
  public function testCacheContexts(): void {
    $breadcrumb = $this->builder()->build($this->routeMatch('paatokset_ahjo_api.browse_policymakers'));
    $contexts = $breadcrumb->getCacheContexts();

    $this->assertContains('languages:language_interface', $contexts);
    $this->assertContains('url.path', $contexts);
  }

}
