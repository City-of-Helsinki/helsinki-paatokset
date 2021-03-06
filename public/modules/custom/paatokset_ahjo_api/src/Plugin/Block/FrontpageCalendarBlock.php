<?php

namespace Drupal\paatokset_ahjo_api\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides Calendar Block.
 *
 * @Block(
 *    id = "frontpage_calendar",
 *    admin_label = @Translation("Paatokset frontpage calendar"),
 *    category = @Translation("Paatokset custom blocks")
 * )
 */
class FrontpageCalendarBlock extends BlockBase {

  /**
   * Build the attributes.
   */
  public function build() {
    return [
      '#cache' => ['contexts' => ['url.path', 'url.query_args']],
      'label' => t('Upcoming meetings'),
      '#attributes' => [
        'class' => ['frontpage-calendar'],
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
