<?php

declare(strict_types = 1);

namespace Drupal\paatokset_ahjo_proxy\Commands;

use Drush\Commands\DrushCommands;
use Drupal\Core\File\FileSystemInterface;
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
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   Logger service.
   * @param \Drupal\paatokset_ahjo_proxy\AhjoProxy $ahjo_proxy
   *   Ahjo Proxy service.
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
   * @option timestamp
   *   Custom timestamp for fetching data earlier than last week as "latest".
   * @option retry
   *   Filename to retry from.
   *
   * @usage ahjo-proxy:aggregate meetings --dataset=latest --filename=meetings_latest.json
   *   Stores latest meetings into meetings_latest.json
   * @usage ahjo-proxy:aggregate meetings --dataset=all --retry=failed_meetings_all.json
   *   Retries failed aggregation based on stored file.
   *
   * @aliases ap:agg
   */
  public function aggregate(string $endpoint, array $options = [
    'dataset' => NULL,
    'filename' => NULL,
    'timestamp' => NULL,
    'retry' => NULL,
  ]): void {

    $allowed_datasets = [
      'all',
      'latest',
    ];

    if (in_array($options['dataset'], $allowed_datasets)) {
      $dataset = $options['dataset'];
    }
    else {
      $dataset = 'latest';
    }

    switch ($endpoint) {
      case 'meetings':
        $timestamp_key = 'start';
        break;

      default:
        $timestamp_key = 'handledsince';
        break;
    }

    if (empty($options['timestamp'])) {
      if ($dataset === 'all') {
        $query_string = $timestamp_key . '=2001-10-01T12:34:45Z';
      }
      else {
        $week_ago = strtotime("-1 week");
        $timestamp = date('Y-m-d\TH:i:s\Z', $week_ago);
        $query_string = $timestamp_key . '=' . $timestamp;
      }
    }
    else {
      $query_string = $timestamp_key . '=' . $options['timestamp'];
    }

    if ($endpoint === 'cases') {
      $query_string .= '&size=500&count_limit=500';
    }

    $data = [];
    $list_key = $this->getListKey($endpoint);

    $this->logger->info('Fetching from ' . $endpoint . ' with query string: ' . $query_string);
    if (!empty($options['retry'])) {
      $this->logger->info('Resuming from file: ' . $options['retry']);
      $data[$list_key] = $this->ahjoProxy->getStatic($options['retry']);
    }
    else {
      $data = $this->ahjoProxy->getData($endpoint, $query_string);
    }


    if (empty($data[$list_key])) {
      $this->logger->info('Empty result.');
      return;
    }

    $this->logger->info('Processing ' . count($data[$list_key]) . ' results.');

    $operations = [];
    $count = 0;
    foreach ($data[$list_key] as $item) {
      $count++;
      $data = [
        'item' => $item,
        'list_key' => $list_key,
        'endpoint' => $endpoint,
        'dataset' => $dataset,
        'count' => $count,
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
   * Store static files into filesystem.
   *
   * @command ahjo-proxy:store-static-files
   *
   * @usage ahjo-proxy:store-static-files
   *   Stores default static files into filesystem (for debugging migrations).
   *
   * @aliases ap:fs
   */
  public function storeStaticFiles() {
    $static_files = [
      'cases_all.json',
      'cases_latest.json',
      'meetings_all.json',
      'meetings_latest.json',
    ];

    foreach ($static_files as $file) {
      $file_path = \Drupal::service('extension.list.module')->getPath('paatokset_ahjo_proxy') . '/static/' . $file;
      $file_contents = file_get_contents($file_path);
      if (!empty($file_contents)) {
        file_save_data($file_contents, 'public://' . $file, FileSystemInterface::EXISTS_REPLACE);
        $this->logger->info('Saved file into public://' . $file);
      }
      else {
        $this->logger->info('Could not load ' . $file);
      }
    }
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

    switch ($endpoint) {
      case 'cases':
        $key = 'cases';
        break;

      case 'meetings':
        $key = 'meetings';
        break;

      default:
        $key = $endpoint;
        break;
    }

    return $key;
  }

}
