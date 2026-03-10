<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Policymakers;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Builds a static breadcrumb for the policymaker browse route.
 *
 * The breadcrumb always stops at "Browse decision-makers", regardless of
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

    $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();

    $route =  match($langcode) {
      'fi' => '/fi/paatoksenteko',
      'sv' => '/sv/beslutsfattande',
      default => '/en/decision-making',
    };

    $breadcrumb->addLink(
      Link::fromTextAndUrl(
      $this->t('Decision-making'),
      Url::fromUserInput($route)
      )
    );

    $breadcrumb->addLink(
      Link::createFromRoute(
        $this->t('Browse decision-makers'),
        'paatokset_ahjo_api.browse_policymakers',
      )
    );

    $breadcrumb->addCacheContexts(['languages:language_interface', 'url.path']);

    return $breadcrumb;
  }

}
