<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a 'PolicymakerBrowserHeroBlock' block.
 */
#[Block(
  id: "policymaker_browser_hero_block",
  admin_label: new TranslatableMarkup("Policymaker Brower Hero Block"),
)]
final class PolicymakerBrowserHeroBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function build() : array {
    $title = new TranslatableMarkup('Browse policymakers',  [], ['context' => 'Policymaker browser']);
    $description = new TranslatableMarkup('Browse existing bodies and authorities.', [], ['context' => 'Policymaker browser']);
    $build['policymaker_browser_hero_block'] = [
      '#theme' => 'policymaker_browser_hero_block',
      '#hero_title' => $title,
      '#first_paragraph_bg' => FALSE,
      '#hero_description' => $description,
    ];

    return $build;
  }

}
