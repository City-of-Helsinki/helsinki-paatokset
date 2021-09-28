<?php

declare(strict_types = 1);

namespace Drupal\paatokset_ahjo_openid_demo\Plugin\migrate_plus\authentication;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate_plus\AuthenticationPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\paatokset_ahjo_openid\AhjoOpenId;

/**
 * Provides a key query parameter for authentication.
 *
 * @Authentication(
 *   id = "ahjo_openid_token",
 *   title = @Translation("Ahjo Open ID Token")
 * )
 */
class AhjoOpenIdToken extends AuthenticationPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Ahjo Open ID service.
   *
   * @var \Drupal\paatokset_ahjo_openid\AhjoOpenId
   */
  protected $ahjoOpenId;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('paatokset_ahjo_openid')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AhjoOpenId $ahjo_open_id) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->ahjoOpenId = $ahjo_open_id;
  }

  /**
   * Performs authentication, returning any options to be added to the request.
   *
   * @inheritdoc
   */
  public function getAuthenticationOptions() {
    // Check if Ahjo Open ID connector is configured.
    if (!$this->ahjoOpenId->isConfigured()) {
      return [];
    }

    // Check if access token is still valid (not expired).
    if ($this->ahjoOpenId->getAuthToken()) {
      $access_token = $this->ahjoOpenId->getAuthToken();
    }
    else {
      // Refresh and return new access token.
      $access_token = $this->ahjoOpenId->refreshAuthToken();
    }

    if (!$access_token) {
      return [];
    }

    return [
      'headers' => [
        'Authorization' => 'Bearer ' . $access_token,
      ],
    ];
  }

}
