<?php

namespace Drupal\paatokset_search\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides Decisions Search Hero Block.
 */
#[Block(
  id: 'decisions_search_hero_block',
  admin_label: new TranslatableMarkup('Decisions Search Hero Block'),
)]
final class DecisionsSearchHeroBlock extends BlockBase {

  /**
   * {@inheritDoc}
   */
  public function build() {
    return [
      '#theme' => 'decisions_search_hero_block',
      '#hero_title' => $this->t('Search decisions', [], ['context' => 'Decisions search']),
      '#hero_description' => $this->t("You can find the City of Helsinki's decision-making body and office holder decisions starting from 2017 in the search.", [], ['context' => 'Decisions search']),
    ];
  }

}
