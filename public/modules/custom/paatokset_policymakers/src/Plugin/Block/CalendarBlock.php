<?php

namespace Drupal\paatokset_policymakers\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides Calendar Block.
 *
 * @Block(
 *    id = "policymaker_calendar",
 *    admin_label = @Translation("Paatokset policymaker calendar"),
 *    category = @Translation("Paatokset custom blocks")
 * )
 */
class CalendarBlock extends BlockBase {

  /**
   * Build the attributes.
   */
  public function build() {
    return [
      '#cache' => ['contexts' => ['url.path', 'url.query_args']],
      '#title' => t('Calendar'),
      '#attributes' => [
        'class' => ['policymaker-calendar'],
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
