<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Hook;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\paatokset_ahjo_api\Entity\CaseBundle;
use Drupal\paatokset_ahjo_api\Service\CaseService;

/**
 * Alters how cores breadcrumbs are rendered.
 */
readonly class Breadcrumbs {

  public function __construct(private CaseService $caseService) {}

  /**
   * Implements hook_system_breadcrumb_alter().
   */
  #[Hook('system_breadcrumb_alter')]
  public function breadcrumb(
    Breadcrumb &$breadcrumb,
    RouteMatchInterface $route_match,
    array $context,
  ): void {
    // Skip admin routes.
    if ($route_match->getRouteObject()?->getOption('_admin_route')) {
      return;
    }

    $links = $breadcrumb->getLinks();

    // @todo remove this once ahjo cases v2 is ready.
    // The v2 case handles titles much better.
    //
    // Case migration sets 'NO TITLE' as a default
    // value, if the case has no title in Ahjo.
    if (array_last($links)?->getText() === 'NO TITLE') {
      $cases = array_filter($route_match->getParameters()->all(), function ($value) {
        return $value instanceof CaseBundle;
      });

      if (empty($case = reset($cases))) {
        return;
      }

      $decision = $this->caseService->guessDecisionFromPath($case);

      // Replace the link text if current route entity is case, the case service
      // has found a decision, and the decision has custom title field set.
      if ($heading = $decision?->getDecisionHeading()) {
        end($links)->setText($heading);

        // We have to recreate entire breadcrumb trail here, because breadcrumb
        // class forbids setting links after they've been set once.
        // @see \Drupal\Core\Breadcrumb\Breadcrumb::setLinks().
        $newBreadcrumb = new Breadcrumb();
        $newBreadcrumb->setLinks($links);
        // Merge cacheable metadata.
        $newBreadcrumb->addCacheTags($breadcrumb->getCacheTags())
          ->addCacheContexts($breadcrumb->getCacheContexts());

        $breadcrumb = $newBreadcrumb;
      }
    }

    // Why does this require query_args context?
    $breadcrumb->addCacheContexts(['url.query_args']);
  }

}
