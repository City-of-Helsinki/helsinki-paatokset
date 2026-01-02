<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\migrate\source;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\paatokset_ahjo_api\AhjoProxy\AhjoProxyClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for Ahjo migrations.
 */
abstract class AhjoSourceBase extends SourcePluginBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  protected $skipCount = TRUE;

  /**
   * The logger service.
   */
  protected LoggerInterface $logger;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Ahjo proxy api.
   */
  protected AhjoProxyClientInterface $client;

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
    $instance->entityTypeManager = $container->get(EntityTypeManagerInterface::class);
    $instance->client = $container->get(AhjoProxyClientInterface::class);
    return $instance;
  }

  /**
   * {@inheritDoc}
   */
  public function __toString() {
    return get_class($this);
  }

}
