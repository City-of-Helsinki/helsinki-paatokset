<?php

namespace Drupal\paatokset_search_form\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for search views.
 */
class SearchViewController extends ControllerBase {

  /**
   * Return render array for decisions.
   */
  public function decisions() {
    return [
      '#theme' => 'paatokset_search_form_decisions',
    ];
  }

  /**
   * Return render array for policymakers.
   */
  public function policymakers() {
    return [
      '#theme' => 'paatokset_search_form_policymakers',
    ];
  }

}
