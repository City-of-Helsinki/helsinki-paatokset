<?php

declare(strict_types=1);

namespace Drupal\paatokset_allu\Client;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\helfi_api_base\Vault\VaultManager;

/**
 * A factory to initialize Allu Settings object.
 */
readonly class SettingsFactory {

  /**
   * Constructs a new instance.
   */
  public function __construct(
    private VaultManager $vaultManager,
    private ConfigFactoryInterface $configFactory,
  ) {
  }

  /**
   * Constructs a new Allu settings object.
   *
   * @return \Drupal\paatokset_allu\Client\Settings
   *   The Allu settings object.
   *
   * @throws \Drupal\paatokset_allu\AlluException
   */
  public function create(): Settings {
    if (!$settings = $this->vaultManager->get('allu')) {
      // Return an empty settings object in case Allu is not
      // configured.
      return new Settings('', '', '');
    }

    $data = $settings->data();

    return new Settings(
      $data->username ?: '',
      $data->password ?: '',
      $this->configFactory->get('paatokset_allu.settings')->get('base_url') ?: ''
    );
  }

}
