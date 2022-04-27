<?php

namespace Drupal\paatokset_ahjo_api\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides All Articles Block.
 *
 * @Block(
 *    id = "all_articles",
 *    admin_label = @Translation("Paatokset all articles"),
 *    category = @Translation("Paatokset custom blocks")
 * )
 */
class ArticlesBlock extends BlockBase {

  /**
   * Build the attributes.
   */
  public function build() {
    return [
      '#cache' => ['contexts' => ['url.path', 'url.query_args']],
      'label' => t('All articles'),
      '#attributes' => [
        'class' => ['all-articles'],
      ],
    ];
  }

  /**
   * Get cache tags.
   */
  public function getCacheTags() {
    return ['node_list:article'];
  }

  /**
   * Get cache contexts.
   */
  public function getCacheContexts() {
    return ['url.path', 'url.query_args'];
  }

}
