<?php

declare(strict_types=1);

namespace Drupal\paatokset_policymakers\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides Calendar Block.
 */
#[Block(
  id: "policymaker_calendar",
  admin_label: new TranslatableMarkup("Paatokset policymaker calendar"),
)]
class CalendarBlock extends BlockBase {

  /**
   * {@inheritDoc}
   */
  public function build(): array {
    return [
      '#cache' => ['contexts' => ['url.path', 'url.query_args']],
      '#title' => $this->t('Calendar'),
      '#attributes' => [
        'class' => ['policymaker-calendar'],
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
