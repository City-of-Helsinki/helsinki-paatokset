<?php

declare(strict_types = 1);

namespace Drupal\paatokset_ahjo_api\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\paatokset_ahjo_api\AhjoQueueWorkerBase;

/**
 * Processes cron queue.
 *
 * @QueueWorker(
 *   id = "ahjo_api_aggregation_queue",
 *   title = @Translation("Ahjo Retry Queue Worker"),
 * )
 */
class AhjoAggregationQueueWorker extends AhjoQueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->ahjoProxy = $container->get('paatokset_ahjo_proxy');
    $instance->logger = $container->get('logger.factory')->get('ahjo_api_aggregation_queue');
    $instance->queueName = 'ahjo_api_aggregation_queue';
    return $instance;
  }

}