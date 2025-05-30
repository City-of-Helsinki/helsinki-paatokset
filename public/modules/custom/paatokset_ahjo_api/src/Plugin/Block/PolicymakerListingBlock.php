<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides Policymaker listing Block.
 */
#[Block(
  id: 'policymaker_listing',
  admin_label: new TranslatableMarkup('Paatokset policymaker listing'),
  category: new TranslatableMarkup('Paatokset custom blocks')
)]
class PolicymakerListingBlock extends BlockBase {

  /**
   * Build the attributes.
   */
  public function build() {
    return [
      'label' => $this->t('Browse policymakers'),
      '#attributes' => [
        'class' => ['policymaker-listing'],
      ],
    ];
  }

  /**
   * Get cache tags.
   */
  public function getCacheTags() {
    return [
      'node_list:policymaker',
      'node_list:trustee',
    ];
  }

  /**
   * Get cache contexts.
   */
  public function getCacheContexts() {
    return ['url.path', 'url.query_args'];
  }

}
