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
  public function decisions() {
    if (getenv('REACT_APP_PROXY_URL')) {
      $proxy_url = getenv('REACT_APP_PROXY_URL');
    }
    else {
      $proxy_url = getenv('REACT_APP_ELASTIC_URL');
    }

    $build = [
      '#markup' => '<div id="paatokset_search" class="paatokset-search--decisions" data-type="decisions" data-url="' . $proxy_url . '"></div>',
      '#attached' => [
        'library' => [
          'paatokset_search/paatokset-search',
        ],
      ],
    ];

    return $build;
  }

}
