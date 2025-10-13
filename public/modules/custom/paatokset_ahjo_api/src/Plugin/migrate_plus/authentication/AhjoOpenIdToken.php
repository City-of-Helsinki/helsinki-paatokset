<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\migrate_plus\authentication;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate_plus\AuthenticationPluginBase;
use Drupal\paatokset_ahjo_api\AhjoOpenId\AhjoOpenId;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a key query parameter for authentication.
 *
 * @Authentication(
 *   id = "ahjo_openid_token",
 *   title = @Translation("Ahjo Open ID Token")
 * )
 */
final class AhjoOpenIdToken extends AuthenticationPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The logger.
   */
  private LoggerInterface $logger;

  /**
   * Ahjo Open Id service.
   */
  private AhjoOpenId $ahjoOpenId;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = new self($configuration, $plugin_id, $plugin_definition);
    $instance->ahjoOpenId = $container->get(AhjoOpenId::class);
    $instance->logger = $container->get('logger.channel.paatokset_ahjo_api');
    return $instance;
  }

  /**
   * Performs authentication, returning any options to be added to the request.
   *
   * @inheritdoc
   */
  public function getAuthenticationOptions($url): array {
    if (!$this->ahjoOpenId->isConfigured()) {
      $this->logger->error('Ahjo Open Id is not configured.');
      return [];
    }

    if (!$this->ahjoOpenId->checkAuthToken()) {
      $this->logger->error('Ahjo Open Id auth token is missing or has expired.');
      return [];
    }

    return [
      'headers' => [
        'Authorization' => 'Bearer ' . $this->ahjoOpenId->getAuthToken(),
      ],
    ];
  }

}
