<?php

declare(strict_types=1);

namespace Drupal\paatokset\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 *
 */
#[Block(
  id: "allu_decisions_search_block",
  admin_label: new TranslatableMarkup("Allu decisions search block"),
)]
class AlluDecisionsSearchBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return [
      '#attached' => [
        'drupalSettings' => [
          'helfi_react_search' => [
            'elastic_proxy_url' => \Drupal::config('elastic_proxy.settings')->get('elastic_proxy_url'),
          ],
        ],
      ],
      '#theme' => 'allu_decisions_search_block',
    ];
  }

}
