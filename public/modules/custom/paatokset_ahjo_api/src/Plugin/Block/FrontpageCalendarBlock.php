<?php

declare(strict_types=1);

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
)]
class FrontpageCalendarBlock extends BlockBase {

  /**
   * {@inheritDoc}
   */
  public function build(): array {
    return [
      '#cache' => ['contexts' => ['url.path', 'url.query_args']],
      'label' => $this->t('Upcoming meetings'),
      '#attributes' => [
        'class' => ['frontpage-calendar'],
      ],
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function getCacheTags(): array {
    return ['search_api_list:meetings'];
  }

  /**
   * {@inheritDoc}
   */
  public function getCacheContexts(): array {
    return ['url.path', 'url.query_args'];
  }

}
