<?php

declare(strict_types=1);

namespace Drupal\paatokset_policymakers\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides Calendar Block.
 */
#[Block(
  id: "policymaker_members",
  admin_label: new TranslatableMarkup("Paatokset policymaker members"),
  category: new TranslatableMarkup('Paatokset custom blocks'),
)]
class MembersBlock extends BlockBase {

  /**
   * {@inheritDoc}
   */
  public function build(): array {
    return [
      '#cache' => ['contexts' => ['url.path', 'url.query_args']],
      '#title' => $this->t('Members'),
      '#attributes' => [
        'class' => ['policymaker-members'],
      ],
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function getCacheTags(): array {
    return ['node_list:trustee'];
  }

  /**
   * {@inheritDoc}
   */
  public function getCacheContexts(): array {
    return ['url.path', 'url.query_args'];
  }

}
