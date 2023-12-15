<?php

namespace Drupal\paatokset_search\Controller;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller class for decisions search page.
 */
class SearchController extends ControllerBase {

  /**
   * Controller for policymaker subpages.
   *
   * @param \Drupal\Core\Config\ImmutableConfig $proxyConfig
   *   The elastic config.
   * @param \Drupal\Core\Config\ImmutableConfig $searchConfig
   *   The search config.
   */
  public function __construct(
    private ImmutableConfig $proxyConfig,
    private ImmutableConfig $searchConfig
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory')->get('elastic_proxy.settings'),
      $container->get('config.factory')->get('paatokset_search.settings'),
    );
  }

  /**
   * Return markup for search page.
   */
  public function decisions(): array {
    $proxyUrl = $this->proxyConfig->get('elastic_proxy_url') ?: '';

    $build = [
      '#markup' => '<div id="paatokset_search" class="paatokset-search--decisions" data-type="decisions" data-url="' . $proxyUrl . '"></div>',
      '#attached' => [
        'library' => [
          'paatokset_search/paatokset-search',
        ],
      ],
    ];

    if ($sentryDsnReact = $this->searchConfig->get('sentry_dsn_react')) {
      $build['#attached']['drupalSettings']['paatokset_react_search']['sentry_dsn_react'] = $sentryDsnReact;
    }

    return $build;
  }

}
