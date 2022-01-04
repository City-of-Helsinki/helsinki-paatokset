<?php

namespace Drupal\paatokset_ahjo_api\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides Policymaker listing Block.
 *
 * @Block(
 *    id = "policymaker_listing",
 *    admin_label = @Translation("Paatokset policymaker listing"),
 *    category = @Translation("Paatokset custom blocks")
 * )
 */
class PolicymakerListingBlock extends BlockBase {

  /**
   * Build the attributes.
   */
  public function build() {
    return [
      '#cache' => ['contexts' => ['url.path', 'url.query_args']],
      'label' => t('Browse policymakers'),
      '#attributes' => [
        'class' => ['policymaker-listing'],
      ],
    ];
  }

  /**
   * Get cache contexts.
   */
  public function getCacheContexts() {
    return ['url.path', 'url.query_args'];
  }

}
