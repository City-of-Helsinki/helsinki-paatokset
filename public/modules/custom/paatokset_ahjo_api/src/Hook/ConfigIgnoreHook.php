<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Config ignore hooks.
 */
class ConfigIgnoreHook {

  /**
   * Implements hook_config_ignore_settings_alter().
   */
  #[Hook('config_ignore_settings_alter')]
  public static function alter(array &$settings): void {
    $settings[] = 'paatokset_ahjo_api.default_texts';
  }

}
