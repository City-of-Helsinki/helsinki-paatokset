<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Breadcrumb\ChainBreadcrumbBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\paatokset_ahjo_api\Entity\CaseBundle;
use Drupal\paatokset_ahjo_api\Entity\Decision;
use Drupal\paatokset_ahjo_api\Service\CaseService;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Routing\Route;

/**
 * Tests breadcrumb hook.
 *
 * @group paatokset_ahjo_api
 */
class BreadcrumbTest extends AhjoKernelTestBase {

  use UserCreationTrait;
  use NodeCreationTrait;
  use ProphecyTrait;

  /**
   * Tests paatokset_ahjo_api_system_breadcrumb_alter().
   */
  public function testBreadcrumbs(): void {
    $caseService = $this->prophesize(CaseService::class);

    $case = $this->prophesize(CaseBundle::class);
    $decision = $this->prophesize(Decision::class);
    $decision->getDecisionHeading()
      ->willReturn('dingdong');

    $caseService
      ->guessDecisionFromPath($case)
      ->willReturn($decision->reveal());

    $this->container->set('paatokset_ahjo_cases', $caseService->reveal());

    $builder = $this->container->get(ChainBreadcrumbBuilderInterface::class);
    $builder->addBuilder($this->createBreadcrumbBuilder([
      Link::createFromRoute('NO TITLE', '<front>'),
    ]), 99999);

    $routeMatch = new RouteMatch(
      '<front>',
      new Route('/{node}', options: ['_admin_route' => FALSE]),
      ['node' => $case->reveal()],
    );

    $breadcrumbs = $builder->build($routeMatch);
    $links = $breadcrumbs->getLinks();

    $this->assertNotEmpty($links);
    $this->assertEquals('dingdong', reset($links)?->getText());
  }

  /**
   * Create new breadcrumb builder.
   */
  private function createBreadcrumbBuilder(array $links): BreadcrumbBuilderInterface {
    return new class($links) implements BreadcrumbBuilderInterface {

      /**
       * Constructs a new instance.
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
