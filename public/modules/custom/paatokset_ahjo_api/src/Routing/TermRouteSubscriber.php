<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\RouteCollection;

/**
 * A simple RouteSubscriber to alter term page routes.
 */
class TermRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    $viewsRoute = $collection->get('view.taxonomy_term.page_1');
    $canonicalRoute = $collection->get('entity.taxonomy_term.canonical');

    if ($viewsRoute) {
      $viewsRoute->setRequirements([
        '_permission' => 'access content',
      ]);
    }

    if ($canonicalRoute) {
      $canonicalRoute->setRequirements([
        '_permission' => 'access content',
      ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Run after the RouteSubscriber of Views and helfi_platform_config,
    // which have priorities of -175 and -180.
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', -185];
    return $events;
  }

}
