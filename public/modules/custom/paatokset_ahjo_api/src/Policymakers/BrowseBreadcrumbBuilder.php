<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Policymakers;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Builds a static breadcrumb for the policymaker browse route.
 *
 * The breadcrumb always stops at "Browse decisionmakers", regardless of
 * which organization is being viewed. Organization-level navigation is
 * handled by the policymaker browser component itself.
 */
class BrowseBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match): bool {
    return $route_match->getRouteName() === 'paatokset_ahjo_api.browse_policymakers';
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match): Breadcrumb {
    $breadcrumb = new Breadcrumb();

    $breadcrumb->addLink(Link::createFromRoute($this->t('Home'), '<front>'));

    // When viewing a specific organization, add a link back to the root
    // browse page. At root level the page title already says
    // "Browse decisionmakers", so no extra crumb is needed.
    if ($route_match->getParameter('org')) {
      $breadcrumb->addLink(
        Link::createFromRoute(
          $this->t('Browse decisionmakers'),
          'paatokset_ahjo_api.browse_policymakers',
        )
      );
    }

    $breadcrumb->addCacheContexts(['languages:language_interface', 'url.path']);

    return $breadcrumb;
  }

}
