<?php

declare(strict_types=1);

namespace Drupal\paatokset\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Theme hooks for paatokset module.
 */
final class ThemeHooks {

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'lupapiste_rss_list' => [
        'variables' => [
          'title' => NULL,
          'content' => NULL,
          'feed_link' => NULL,
        ],
        'template' => 'lupapiste-rss-list',
      ],
      'lupapiste_rss_item' => [
        'variables' => [
          'item' => NULL,
        ],
        'template' => 'lupapiste-rss-item',
      ],
      'paatokset_dictionary_banner' => [
        'variables' => [
          'dictionary_url' => NULL,
        ],
      ],
    ];
  }

}
