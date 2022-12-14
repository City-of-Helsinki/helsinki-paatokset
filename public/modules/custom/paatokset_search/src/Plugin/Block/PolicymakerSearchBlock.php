<?php

namespace Drupal\paatokset_search\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides Block for policymaker search.
 *
 * @Block(
 *    id = "policymaker_search_block",
 *    admin_label = @Translation("Paatokset policymaker search"),
 *    category = @Translation("Paatokset custom blocks")
 * )
 */
class PolicymakerSearchBlock extends BlockBase {

  /**
   * Build the attributes.
   */
  public function build() {
    if (getenv('REACT_APP_PROXY_URL')) {
      $proxy_url = getenv('REACT_APP_PROXY_URL');
    }
    else {
      $proxy_url = getenv('REACT_APP_ELASTIC_URL');
    }

    $build = [
      '#markup' => '<div class="paatokset-search-wrapper"><div id="paatokset_search" data-type="policymakers" data-url="' . $proxy_url . '"></div></div>',
      '#attributes' => [
        'class' => ['policymaker-search'],
      ],
      '#attached' => [
        'library' => [
          'paatokset_search/paatokset-search',
        ],
      ],
    ];

    return $build;
  }

}
