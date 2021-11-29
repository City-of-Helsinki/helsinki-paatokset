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
   * @option start
   *   Custom timestamp for fetching data.
   * @option end
   *   Custom timestamp for fetching data.
   * @option retry
   *   Filename to retry from.
   * @option append
   *   File to append to. Useful when retrying.
   * @option filename
   *   Filename to use instead of default. Can be used to split/batch results.
   *
   * @usage ahjo-proxy:aggregate meetings --dataset=latest
   *   Stores latest meetings into meetings_latest.json
   * @usage ahjo-proxy:aggregate meetings --dataset=all --retry=failed_meetings_all.json --append=meetings_all.json
   *   Retries failed aggregation based on stored file.
   *
   * @aliases ap:agg
   */
  public function aggregate(string $endpoint, array $options = [
    'dataset' => NULL,
    'start' => NULL,
    'end' => NULL,
    'retry' => NULL,
    'filename' => NULL,
    'append' => NULL,
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
        $timestamp_key_end = 'end';
        break;

      default:
        $timestamp_key = 'handledsince';
        $timestamp_key_end = 'handledbefore';
        break;
    }

    if (empty($options['start'])) {
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
      $query_string = $timestamp_key . '=' . $options['start'];
    }

    if (!empty($options['end'])) {
      $query_string .= '&' . $timestamp_key_end . '=' . $options['end'];
    }

    if ($endpoint === 'cases' || $endpoint === 'decisions') {
      $query_string .= '&size=1000&count_limit=1000';
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

    if (!empty($options['append'])) {
      $append_results = $this->ahjoProxy->getStatic($options['append']);
      $this->logger->info('Combining with file: ' . $options['append']);
    }

    if (!empty($options['filename'])) {
      $filename = $options['filename'];
      $this->logger->info('Using filename: ' . $filename);
    }
    else {
      $filename = NULL;
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
        'append' => NULL,
        'filename' => $filename,
      ];

      if ($count === 1 && !empty($append_results[$list_key])) {
        $data['append'] = $append_results[$list_key];
      }

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
      'decisions_all.json',
      'decisions_latest.json',
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

      case 'decisions':
        $key = 'decisions';
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
