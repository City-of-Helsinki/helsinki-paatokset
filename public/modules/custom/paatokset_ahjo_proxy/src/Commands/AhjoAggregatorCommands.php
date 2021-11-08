<?php

declare(strict_types = 1);

namespace Drupal\paatokset_ahjo_proxy\Commands;

use Drush\Commands\DrushCommands;
use Drupal\paatokset_ahjo_proxy\AhjoProxy;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Ahjo Aggregator drush commands.
 *
 * @package Drupal\paatokset_ahjo_proxy\Commands
 */
class AhjoAggregatorCommands extends DrushCommands {

  /**
   * Ahjo proxy service.
   *
   * @var \Drupal\paatokset_ahjo_proxy\AhjoProxy
   */
  protected $ahjoProxy;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructor for Ahjo Aggregator Commands.
   *

   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   Logger service.
   */
  public function __construct(LoggerChannelFactoryInterface $logger_factory, AhjoProxy $ahjo_proxy) {
    $this->ahjoProxy = $ahjo_proxy;
    $this->logger = $logger_factory->get('paatokset_ahjo_proxy');
  }

  /**
   * Aggregates data from Ahjo API.
   *
   * @param string $endpoint
   *   Endpoint to aggregate data from.
   * @param array $options
   *   Additional options for the command.
   *
   * @command ahjo-proxy:aggregate
   *
   * @option dataset
   *   Which dataset to get (latest, all, etc)
   * @option filename
   *   Which file to store aggregated data into.
   *
   * @usage ahjo-proxy:aggregate meetings --dataset=latest --filename=meetings_latest.json
   *   Cleans broken revision from migration imported nodes.
   *
   * @aliases ap:agg
   */
  public function aggregate(string $endpoint, array $options = [
    'dataset' => NUll,
    'filename' => NULL,
  ]): void {

    $allowed_datasets = [
      'all',
      'latest'
    ];

    if (in_array($options['dataset'], $allowed_datasets)) {
      $dataset = $options['dataset'];
    }
    else {
      $dataset = 'latest';
    }

    if ($dataset === 'latest') {
      $week_ago = strtotime("-1 week");
      $timestamp = date('Y-m-dTH:i:sZ', $week_ago);
      $query_string = 'start=' . $timestamp;
    }
    else {
      $query_string = 'start=2001-10-01T12:34:45Z';
    }

    $data = $this->ahjoProxy->getData($endpoint, $query_string);

    if (empty($data[$endpoint])) {
      $this->logger->info('Empty result.');
    }

    $list_key = $this->getListKey($endpoint);

    $operations = [];
    foreach ($data[$list_key] as $item) {
      $data = [
        'item' => $item,
        'endpoint' => $endpoint,
        'dataset' => $dataset,
      ];
      $operations[] = [
        '\Drupal\paatokset_ahjo_proxy\AhjoProxy::processBatchItem',
        [$data],
      ];
    }


    batch_set([
      'title' => 'Aggregating: ' . $endpoint . ' with dataset:' . $dataset,
      'operations' => $operations,
      'finished' => '\Drupal\paatokset_ahjo_proxy\AhjoProxy::finishBatch',
    ]);

    drush_backend_batch_process();
  }

  /**
   * Get list key for data array.
   *
   * @param string $endpoint
   *   Endpoint to get key for.
   *
   * @return string|null
   *   List key.
   */
  private function getListKey(string $endpoint): ?string {
    switch($endpoint) {
      default:
        $key = $endpoint;
    }

    return $key;
  }

}
