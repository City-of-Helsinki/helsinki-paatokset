<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Breadcrumb\ChainBreadcrumbBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Routing\Route;

/**
 * Tests breadcrumb hook.
 */
#[Group('paatokset_ahjo_api')]
class BreadcrumbTest extends AhjoEntityKernelTestBase {

  use NodeCreationTrait;

  /**
   * Tests paatokset_ahjo_api_system_breadcrumb_alter().
   */
  public function testBreadcrumbs(): void {
    $case = $this->createNode([
      'type' => 'case',
      'langcode' => 'en',
      'field_diary_number' => '123',
    ]);
    $this->createNode([
      'type' => 'decision',
      'title' => 'Test decision',
      'langcode' => 'en',
      'field_diary_number' => '123',
      'field_decision_case_title' => 'dingdong',
    ]);

    $builder = $this->container->get(ChainBreadcrumbBuilderInterface::class);
    $builder->addBuilder($this->createBreadcrumbBuilder([
      Link::createFromRoute('NO TITLE', '<front>'),
    ]), 99999);

    $routeMatch = new RouteMatch(
      '<front>',
      new Route('/{node}', options: ['_admin_route' => FALSE]),
      ['node' => $case],
    );

    $breadcrumbs = $builder->build($routeMatch);
    $links = $breadcrumbs->getLinks();

    $this->assertNotEmpty($links);
    $this->assertEquals('dingdong', reset($links)?->getText());
  }

  /**
   * Create new breadcrumb builder.
   *
   * @phpstan-param \Drupal\Core\Link[] $links
   */
  private function createBreadcrumbBuilder(array $links): BreadcrumbBuilderInterface {
    return new class($links) implements BreadcrumbBuilderInterface {

      /**
       * Constructs a new instance.
       *
       * @phpstan-param \Drupal\Core\Link[] $links
       */
      public function __construct(private readonly array $links) {}

      /**
       * {@inheritDoc}
       */
      public function applies(RouteMatchInterface $route_match): bool {
        return TRUE;
      }

      /**
       * {@inheritDoc}
       */
      public function build(RouteMatchInterface $route_match): Breadcrumb {
        return (new Breadcrumb())
          ->setLinks($this->links);
      }

    };
  }

}
