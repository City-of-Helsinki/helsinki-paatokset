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
  id: 'meetings_calendar',
  admin_label: new TranslatableMarkup('Paatokset meetings calendar'),
  category: new TranslatableMarkup('Paatokset custom blocks'),
)]
class MeetingsCalendarBlock extends BlockBase {

  /**
   * {@inheritDoc}
   */
  public function build(): array {
    return [
      '#cache' => ['contexts' => ['url.path', 'url.query_args']],
      'label' => $this->t('Upcoming meetings'),
      '#attributes' => [
        'class' => ['meetings-calendar'],
      ],
    ];
  }

  /**
   * Get cache tags.
   */
  public function getCacheTags(): array {
    return ['node_list:meeting'];
  }

  /**
   * Get cache contexts.
   */
  public function getCacheContexts(): array {
    return ['url.path', 'url.query_args'];
  }

}
