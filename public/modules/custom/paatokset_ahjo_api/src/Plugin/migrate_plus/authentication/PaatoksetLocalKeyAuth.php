<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\migrate_plus\authentication;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate_plus\AuthenticationPluginBase;

/**
 * Provides an api key header for authentication.
 *
 * @Authentication(
 *   id = "paatokset_local_key_auth",
 *   title = @Translation("Local API key auth.")
 * )
 */
class PaatoksetLocalKeyAuth extends AuthenticationPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Holds the environment variable.
   */
  public const ENV_API_KEY = 'LOCAL_PROXY_API_KEY';

  /**
   * Performs authentication, returning any options to be added to the request.
   *
   * @inheritdoc
   */
  public function getAuthenticationOptions($url): array {
    if (!empty(getenv(self::ENV_API_KEY))) {
      return [
        'headers' => [
          'api-key' => getenv(self::ENV_API_KEY),
        ],
      ];
    }

    throw new \InvalidArgumentException("Missing proxy api key");
  }

}
