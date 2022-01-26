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
    $build = [
      '#markup' => '<div id="paatokset_search" data-type="decisions" data-url="' . getenv('REACT_APP_ELASTIC_URL') . '"></div>',
      '#attached' => [
        'library' => [
          'paatokset_search/paatokset-search',
        ],
      ],
    ];

    return $build;
  }

}
