<?php

namespace Drupal\paatokset_search_form\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a block containing search form.
 *
 * @Block(
 *  id="search_form_block",
 *  admin_label=@Translation("Search block")
 * )
 */
class SearchBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    if (getenv('REACT_APP_PROXY_URL')) {
      $proxy_url = getenv('REACT_APP_PROXY_URL');
    }
    else {
      $proxy_url = getenv('REACT_APP_ELASTIC_URL');
    }

    $build = [
      '#markup' => '<div class="paatokset-search-wrapper"><div id="paatokset_search" data-type="frontpage" data-url="' . $proxy_url . '"></div></div>',
      '#attributes' => [
        'class' => ['paatokset-search--frontpage'],
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
