<?php

namespace Drupal\paatokset_ahjo_api\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides Calendar Block.
 *
 * @Block(
 *    id = "meetings_calendar",
 *    admin_label = @Translation("Paatokset meetings calendar"),
 *    category = @Translation("Paatokset custom blocks")
 * )
 */
class MeetingsCalendarBlock extends BlockBase {

  /**
   * Build the attributes.
   */
  public function build() {
    return [
      '#cache' => ['contexts' => ['url.path', 'url.query_args']],
      'label' => t('Upcoming meetings'),
      '#attributes' => [
        'class' => ['meetings-calendar'],
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
