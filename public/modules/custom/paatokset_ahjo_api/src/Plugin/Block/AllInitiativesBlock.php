<?php

namespace Drupal\paatokset_ahjo_api\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides All Initiatives Block.
 *
 * @Block(
 *    id = "all_initiatives",
 *    admin_label = @Translation("Paatokset all initiatives"),
 *    category = @Translation("Paatokset custom blocks")
 * )
 */
class AllInitiativesBlock extends BlockBase {

  /**
   * Build the attributes.
   */
  public function build() {
    return [
      '#cache' => ['contexts' => ['url.path', 'url.query_args']],
      'label' => t('All initiatives'),
      '#attributes' => [
        'class' => ['all-initiatives'],
      ],
    ];
  }

  /**
   * Get cache tags.
   */
  public function getCacheTags() {
    return ['node_list:trustee'];
  }

  /**
   * Get cache contexts.
   */
  public function getCacheContexts() {
    return ['url.path', 'url.query_args'];
  }

}
