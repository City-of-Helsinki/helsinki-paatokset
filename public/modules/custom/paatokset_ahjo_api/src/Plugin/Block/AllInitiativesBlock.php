<?php

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
  category: new TranslatableMarkup('Paatokset custom blocks')
)]
class AllInitiativesBlock extends BlockBase {

  /**
   * Build the attributes.
   */
  public function build() {
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
  public function getCacheTags() {
    return ['node_list:trustee'];
  }

  /**
   * Get cache contexts.
   */
  public function getCacheContexts() {
    return ['url.path', 'url.query_args'];
  }

}
