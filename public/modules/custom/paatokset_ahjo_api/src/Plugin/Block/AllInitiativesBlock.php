<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides All Initiatives Block.
 */
#[Block(
  id: 'all_initiatives',
  admin_label: new TranslatableMarkup('Paatokset all initiatives'),
)]
class AllInitiativesBlock extends BlockBase {

  /**
   * Build the attributes.
   */
  public function build(): array {
    return [
      '#cache' => ['contexts' => ['url.path', 'url.query_args']],
      'label' => $this->t('All initiatives'),
      '#attributes' => [
        'class' => ['all-initiatives'],
      ],
    ];
  }

  /**
   * Get cache tags.
   */
  public function getCacheTags(): array {
    return ['node_list:trustee'];
  }

  /**
   * Get cache contexts.
   */
  public function getCacheContexts(): array {
    return ['url.path', 'url.query_args'];
  }

}
