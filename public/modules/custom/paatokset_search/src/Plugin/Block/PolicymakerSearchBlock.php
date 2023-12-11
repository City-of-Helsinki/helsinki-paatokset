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
   * {@inheritDoc}
   */
  public function build(): array {
    $config = \Drupal::config('elastic_proxy.settings');
    $proxyUrl = $config->get('elastic_proxy_url') ?: '';

    $build = [
      '#markup' => '<div class="paatokset-search-wrapper"><div id="paatokset_search" data-type="policymakers" data-url="' . $proxyUrl . '"></div></div>',
      '#attributes' => [
        'class' => ['policymaker-search'],
      ],
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
