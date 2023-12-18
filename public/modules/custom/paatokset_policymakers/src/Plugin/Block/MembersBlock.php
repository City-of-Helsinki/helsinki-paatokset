<?php

namespace Drupal\paatokset_policymakers\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides Calendar Block.
 *
 * @Block(
 *    id = "policymaker_members",
 *    admin_label = @Translation("Paatokset policymaker members"),
 *    category = @Translation("Paatokset custom blocks")
 * )
 */
class MembersBlock extends BlockBase {

  /**
   * Build the attributes.
   */
  public function build() {
    return [
      '#cache' => ['contexts' => ['url.path', 'url.query_args']],
      '#title' => $this->t('Members'),
      '#attributes' => [
        'class' => ['policymaker-members'],
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
