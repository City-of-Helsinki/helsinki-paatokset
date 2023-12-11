<?php

namespace Drupal\paatokset_search\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller.
 */
class SearchController extends ControllerBase {

  /**
   * Return markup for search page.
   */
  public function decisions(): array {
    $config = \Drupal::config('elastic_proxy.settings');
    $proxyUrl = $config->get('elastic_proxy_url') ?: '';

    $build = [
      '#markup' => '<div id="paatokset_search" class="paatokset-search--decisions" data-type="decisions" data-url="' . $proxyUrl . '"></div>',
      '#attached' => [
        'library' => [
          'paatokset_search/paatokset-search',
        ],
      ],
    ];

    $react_search_config = \Drupal::config('paatokset_search.settings');
    if ($sentry_dsn_react = $react_search_config->get('sentry_dsn_react')) {
      $build['#attached']['drupalSettings']['paatokset_react_search']['sentry_dsn_react'] = $sentry_dsn_react;
    }

    return $build;
  }

}
