<?php

namespace Drupal\paatokset_ahjo_api\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides Decision tree Block.
 */
#[Block(
  id: 'decision_tree',
  admin_label: new TranslatableMarkup('Paatokset decision tree'),
  category: new TranslatableMarkup('Paatokset custom blocks')
)]
class DecisionTreeBlock extends BlockBase {

  /**
   * Build the attributes.
   */
  public function build() {
    return [
      'label' => $this->t('Decision tree'),
      '#attributes' => [
        'class' => ['decision-tree'],
      ],
    ];
  }

  /**
   * Get cache contexts.
   */
  public function getCacheContexts() {
    return ['url.path', 'url.query_args'];
  }

}
