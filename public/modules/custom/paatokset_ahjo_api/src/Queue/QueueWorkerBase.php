<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Queue;

use Drupal\Core\DependencyInjection\AutowiredInstanceTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase as CoreQueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A Base class for Queue workers with autowiring support.
 *
 * @todo Remove once https://www.drupal.org/project/drupal/issues/3452852
 * is merged.
 *
 * QueueWorkerBase extends \Drupal\Core\Component\Plugin\PluginBase (instead of
 * \Drupal\Core\Plugin\PluginBase). The component plugin base does not have
 * autowire support, so we have to manually call the ::createInstanceAutowired()
 * method.
 */
abstract class QueueWorkerBase extends CoreQueueWorkerBase implements ContainerFactoryPluginInterface {

  use AutowiredInstanceTrait;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): static {
    return static::createInstanceAutowired($container, $configuration, $plugin_id, $plugin_definition);
  }

}
