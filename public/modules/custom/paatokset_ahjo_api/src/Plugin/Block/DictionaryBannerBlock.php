<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides Decisions Dictionary block.
 */
#[Block(
  id: 'dictionary_banner',
  admin_label: new TranslatableMarkup('Paatokset decisions dictionary banner'),
  category: new TranslatableMarkup('Paatokset custom blocks'),
)]
class DictionaryBannerBlock extends BlockBase {

  /**
   * {@inheritDoc}
   */
  public function build(): array {
    return [
      '#cache' => ['contexts' => ['url.path', 'url.query_args']],
      '#label' => $this->t('Decisions dictionary banner'),
      '#attributes' => [
        'class' => ['decisions-dictionary'],
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
