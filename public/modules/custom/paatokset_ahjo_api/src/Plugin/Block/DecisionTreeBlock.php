<?php

declare(strict_types=1);

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
)]
class DecisionTreeBlock extends BlockBase {

  /**
   * {@inheritDoc}
   */
  public function build(): array {
    return [
      'label' => $this->t('Decision tree'),
      '#attributes' => [
        'class' => ['decision-tree'],
      ],
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function getCacheContexts(): array {
    return ['url.path', 'url.query_args'];
  }

}
