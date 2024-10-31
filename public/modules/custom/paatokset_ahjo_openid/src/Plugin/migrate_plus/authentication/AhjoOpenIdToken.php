<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_openid\Plugin\migrate_plus\authentication;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Utility\Error;
use Drupal\migrate_plus\AuthenticationPluginBase;
use Drupal\paatokset_ahjo_openid\AhjoOpenId;
use Drupal\paatokset_ahjo_openid\AhjoOpenIdException;
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
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('paatokset_ahjo_openid'),
      $container->get('logger.channel.paatokset_ahjo_openid'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    private readonly AhjoOpenId $ahjoOpenId,
    private readonly LoggerInterface $logger,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * Performs authentication, returning any options to be added to the request.
   *
   * @inheritdoc
   */
  public function getAuthenticationOptions(): array {
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
