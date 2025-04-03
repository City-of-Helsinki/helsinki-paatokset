<?php

namespace Drupal\paatokset_ahjo_api\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides Calendar Block.
 */
#[Block(
  id: 'frontpage_calendar',
  admin_label: new TranslatableMarkup('Paatokset frontpage calendar'),
  category: new TranslatableMarkup('Paatokset custom blocks')
)]
class FrontpageCalendarBlock extends BlockBase {

  /**
   * Build the attributes.
   */
  public function build() {
    return [
      '#cache' => ['contexts' => ['url.path', 'url.query_args']],
      'label' => $this->t('Upcoming meetings'),
      '#attributes' => [
        'class' => ['frontpage-calendar'],
      ],
    ];
  }

  /**
   * Get cache tags.
   */
  public function getCacheTags() {
    return ['search_api_list:meetings'];
  }

  /**
   * Get cache contexts.
   */
  public function getCacheContexts() {
    return ['url.path', 'url.query_args'];
  }

}
