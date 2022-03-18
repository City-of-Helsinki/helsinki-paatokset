<?php

namespace Drupal\paatokset_ahjo_api\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides Decisions Dictionary block.
 *
 * @Block(
 *    id = "dictionary_banner",
 *    admin_label = @Translation("Paatokset decisions dictionary banner"),
 *    category = @Translation("Paatokset custom blocks")
 * )
 */
class DictionaryBannerBlock extends BlockBase {

  /**
   * Build the attributes.
   */
  public function build() {
    return [
      '#cache' => ['contexts' => ['url.path', 'url.query_args']],
      '#label' => t('Decisions dictionary banner'),
      '#attributes' => [
        'class' => ['decisions-dictionary'],
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
