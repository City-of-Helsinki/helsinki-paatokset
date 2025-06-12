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
  category: new TranslatableMarkup('Paatokset custom blocks'),
)]
class PolicymakerListingBlock extends BlockBase {

  /**
   * {@inheritDoc}
   */
  public function build(): array {
    return [
      'label' => $this->t('Browse policymakers'),
      '#attributes' => [
        'class' => ['policymaker-listing'],
      ],
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function getCacheTags(): array {
    return [
      'node_list:policymaker',
      'node_list:trustee',
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function getCacheContexts(): array {
    return ['url.path', 'url.query_args'];
  }

}
