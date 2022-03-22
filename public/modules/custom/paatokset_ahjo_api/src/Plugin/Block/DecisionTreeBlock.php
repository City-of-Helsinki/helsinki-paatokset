<?php

namespace Drupal\paatokset_ahjo_api\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides Decision tree Block.
 *
 * @Block(
 *    id = "decision_tree",
 *    admin_label = @Translation("Paatokset decision tree"),
 *    category = @Translation("Paatokset custom blocks")
 * )
 */
class DecisionTreeBlock extends BlockBase {

  /**
   * Build the attributes.
   */
  public function build() {
    return [
      'label' => t('Decision tree'),
      '#attributes' => [
        'class' => ['decision-tree'],
      ],
    ];
  }

  /**
   * Get cache tags.
   */
  public function getCacheTags() {
    return ['node_list:meeting'];
  }

  /**
   * Get cache contexts.
   */
  public function getCacheContexts() {
    return ['url.path', 'url.query_args'];
  }

}
