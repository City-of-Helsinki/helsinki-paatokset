<?php

declare(strict_types=1);

namespace Drupal\paatokset_search\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides Policymaker Search Hero Block.
 */
#[Block(
  id: 'policymaker_search_hero_block',
  admin_label: new TranslatableMarkup('Policymaker Search Hero Block'),
)]
final class PolicymakerSearchHeroBlock extends BlockBase {

  /**
   * {@inheritDoc}
   */
  public function build(): array {
    return [
      '#theme' => 'policymaker_search_hero_block',
      '#hero_title' => $this->t('Search decision-makers', [], ['context' => 'Policymaker search']),
      '#hero_description' => $this->t('Search for a decision-making body, office holder or councillor.', [], ['context' => 'Policymaker search']),
    ];
  }

}
