<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_openid;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * A factory to initialize a Settings object.
 */
final readonly class SettingsFactory {

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The vault manager.
   */
  public function __construct(
    private ConfigFactoryInterface $configFactory,
  ) {
  }

  /**
   * Constructs a new Settings object.
   *
   * @return \Drupal\paatokset_ahjo_openid\Settings
   *   The PubSub settings object.
   */
  public function create() : Settings {
    $config = $this->configFactory->get('paatokset_ahjo_openid.settings');

    $secret = getenv('PAATOKSET_OPENID_SECRET');

    return new Settings(
      $config->get('auth_url') ?: '',
      $config->get('token_url') ?: '',
      $config->get('callback_url') ?: '',
      $config->get('client_id') ?: '',
      $config->get('scope') ?: '',
      $secret ?: '',
    );
  }

}
