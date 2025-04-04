<?php

namespace Drupal\paatokset_search\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\paatokset_search\SearchManager;

/**
 * Controller class for decisions search page.
 */
class SearchController extends ControllerBase {

  /**
   * Controller for policymaker subpages.
   *
   * @param \Drupal\paatokset_search\SearchManager $searchManager
   *   The search manager.
   */
  public function __construct(
    private readonly SearchManager $searchManager,
  ) {
  }

  /**
   * Return markup for search page.
   */
  public function decisions(): array {
    return $this->searchManager->build('decisions', ['paatokset-search--decisions']);
  }

}
