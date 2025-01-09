<?php

declare(strict_types=1);

namespace Drupal\paatokset_submenus\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Provides Agendas Submenu Block.
 */
#[Block(
  id: 'policymaker_side_nav_mobile',
  admin_label: new TranslatableMarkup('Policymaker mobile navigation'),
  category: new TranslatableMarkup('Paatokset custom blocks')
)]
class PolicymakerMobileNav extends PolicymakerSideNav {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $options = $this->itemsToOptions();

    return [
      '#options' => $options['options'],
      '#current_option' => $options['current_option'],
    ];
  }

  /**
   * Transform items to HDS dropdown options.
   *
   * @return array
   *   Array of options + current option
   */
  private function itemsToOptions(): array {
    if (!is_array($this->items)) {
      return [];
    }

    $currentPath = Url::fromRoute('<current>')->toString();
    $currentOption = NULL;
    $options = [];
    foreach ($this->items as $option) {
      $option = [
        'label' => $option['title'],
        'value' => $option['url']->toString(),
      ];

      if ($option['value'] === $currentPath) {
        $currentOption = $option;
      }

      $options[] = $option;
    }

    return [
      'options' => json_encode($options),
      'current_option' => json_encode($currentOption),
    ];
  }

}
