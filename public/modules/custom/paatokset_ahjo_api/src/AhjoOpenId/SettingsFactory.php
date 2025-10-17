<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\AhjoOpenId;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;

/**
 * A factory to initialize a Settings object.
 */
final readonly class SettingsFactory {

  public function __construct(
    private ConfigFactoryInterface $configFactory,
    private LanguageManagerInterface $languageManager,
  ) {
  }

  /**
   * Constructs a new Settings object.
   *
   * @return \Drupal\paatokset_ahjo_api\AhjoOpenId\Settings
   *   The settings object.
   */
  public function create() : Settings {
    $config = $this->configFactory
      ->get('paatokset_ahjo_api.settings')
      ->get('openid_settings');

    $secret = getenv('PAATOKSET_OPENID_SECRET');

    $callbackUrl = Url::fromRoute('paatokset_ahjo_openid.callback', options: [
      'language' => $this->languageManager->getLanguage('zxx'),
    ])
      ->setAbsolute()
      ->toString();

    return new Settings(
      $config['auth_url'] ?? '',
      $config['token_url'] ?? '',
      $callbackUrl,
      $config['client_id'] ?? '',
      $config['scope'] ?? '',
      $secret ?: '',
    );
  }

}
