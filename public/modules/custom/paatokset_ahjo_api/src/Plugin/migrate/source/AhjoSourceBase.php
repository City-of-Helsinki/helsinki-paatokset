<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\migrate\source;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\paatokset_ahjo_proxy\AhjoProxy;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for Ahjo migrations.
 */
abstract class AhjoSourceBase extends SourcePluginBase implements ContainerFactoryPluginInterface {

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * Ahjo proxy service.
   *
   * @var \Drupal\paatokset_ahjo_proxy\AhjoProxy
   */
  protected AhjoProxy $ahjoProxy;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ?MigrationInterface $migration = NULL,
  ): static {
    $instance = new static($configuration, $plugin_id, $plugin_definition, $migration);
    $instance->logger = $container->get('logger.channel.paatokset_ahjo_api');
    $instance->ahjoProxy = $container->get('paatokset_ahjo_proxy');
    return $instance;
  }

  /**
   * {@inheritDoc}
   */
  public function __toString() {
    return get_class($this);
  }

}
