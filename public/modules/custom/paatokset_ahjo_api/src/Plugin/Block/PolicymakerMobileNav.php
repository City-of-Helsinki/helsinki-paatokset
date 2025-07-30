<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;

/**
 * Provides Agendas Submenu Block.
 */
#[Block(
  id: 'policymaker_side_nav_mobile',
  admin_label: new TranslatableMarkup('Policymaker mobile navigation'),
  category: new TranslatableMarkup('Paatokset custom blocks'),
)]
class PolicymakerMobileNav extends PolicymakerSideNav {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $options = $this->itemsToOptions();
    $currentOption = $options['current_option'];

    $variables = [
      '#theme' => 'policymaker_side_navigation_mobile',
      '#current_option' => $currentOption,
      '#attached' => [
        'library' => [
          'hdbt/sidebar-menu-toggle',
        ],
      ],
    ];

    // Create fake menu items for mobile navigation.
    foreach ($options['options'] as $option) {
      $variables['#items'][] = [
        'title' => $option->label,
        'url' => Url::fromUserInput($option->value),
        'attributes' => new Attribute(),
        'in_active_trail' => $this->isActiveTrail($currentOption, $option),
        'is_currentPage' => $this->isActiveTrail($currentOption, $option),
      ];
    }

    return $variables;
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
      'options' => $options,
      'current_option' => $currentOption,
    ];
  }

  /**
   * Helper function to check if the current option is in active trail.
   */
  private function isActiveTrail($currentOption, $option): bool {
    if (!$currentOption || !$option) {
      return FALSE;
    }

    return $currentOption->value === $option->value;
  }

}
