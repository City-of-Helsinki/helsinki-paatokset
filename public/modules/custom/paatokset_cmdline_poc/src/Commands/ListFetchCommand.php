<?php

declare(strict_types=1);

namespace App\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\TransferStats;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use GuzzleHttp\Psr7\Response;

/**
 * Command for aggregating data outside of Drupal.
 */
final class ListFetchCommand extends Command {

  /**
   * Command name.
   *
   * @var string
   */
  protected static $defaultName = 'app:list-fetch';

  /**
   * Ahjo API base URL.
   */
  protected const BASE_URL = 'https://ahjo.hel.fi:9802/ahjorest/v1/';

  /**
   * URL to fetch access token from.
   */
  protected const TOKEN_URL = 'https://nginx-paatokset-test.agw.arodevtest.hel.fi/fi/admin/ahjo-open-id/token';

  /**
   * Constructs list fetch command.
   *
   * @param \GuzzleHttp\Client $client
   *   HTTP Client.
   */
  public function __construct(private Client $client) {
    parent::__construct();
  }

  /**
   * Configuration, adds arguments for command.
   */
  public function configure() {
    $this->addArgument(
      'endpoint',
      InputArgument::REQUIRED,
      'Endpoint and list key to fetch data from.'
    );

    $this->addArgument(
      'start',
      InputArgument::REQUIRED,
      'Timestamp, where to start dataset.'
    );

    $this->addArgument(
      'end',
      InputArgument::REQUIRED,
      'Timestamp, where to end dataset.'
    );

    $this->addArgument(
      'apikey',
      InputArgument::REQUIRED,
      'API key to fetch access token with.',
    );
  }

  /**
   * Executes command.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *   Input for command.
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *   Output for command.
   *
   * @return int
   *   Exit code.
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $start_time = microtime(TRUE);
    $endpoint = $input->getArgument('endpoint');
    $start = $input->getArgument('start');
    $end = $input->getArgument('end');
    $api_key = $input->getArgument('apikey');
    $access_token = $this->getAccessToken($api_key);

    if (!$access_token) {
      $output->writeln('No access token.');
      return 1;
    }

    $list_url = $this->buildListUrl($endpoint, $start, $end);
    $output->writeln('Fetching list from: ' . $list_url);
    $list_res = $this->doRequest($list_url, $output, $access_token);
    if (!$list_res) {
      return 1;
    }

    $output->writeln('Status: ' . $list_res->getStatusCode());

    if ($list_res->getStatusCode() !== 200) {
      return 1;
    }

    $list = (string) $list_res->getBody();
    $list = json_decode($list, TRUE);
    if (empty($list['meetings'])) {
      $output->writeln('Empty list.');
      return 1;
    }
    $output->writeln('Total: ' . count($list[$endpoint]));

    $count = 0;
    foreach ($list[$endpoint] as $item) {
      if (empty($item['links'])) {
        continue;
      }

      $count++;
      $item_url = $item['links'][0]['href'];
      $output->writeln($count . '. Fetching from: ' . $item_url);
      $content_res = $this->doRequest($item_url, $output, $access_token);
      if ($content_res->getStatusCode() !== 200) {
        $output->writeln('Status: ' . $content_res->getStatusCode());
      }
    }

    $end_time = microtime(TRUE);
    $total_time = ($end_time - $start_time);
    $output->writeln('Took ' . $total_time . ' seconds.');
    return 0;
  }

  /**
   * Builds API list URL.
   *
   * @param string $endpoint
   *   Endpoint to get data from.
   * @param string $start
   *   Timestamp to start filtering from.
   * @param string $end
   *   Timestamp to end filtering on.
   *
   * @return string
   *   List URL.
   */
  protected function buildListUrl(string $endpoint, string $start, string $end): string {
    $list_url = self::BASE_URL . $endpoint;
    $list_url .= '?count_limit=1000&size=1000';
    if ($endpoint === 'meetings') {
      $list_url .= '&start=' . $start;
      $list_url .= '&end=' . $end;
    }
    else {
      $list_url .= '&handledsince=' . $start;
      $list_url .= '&handledbefore=' . $end;
    }

    return $list_url;
  }

  /**
   * Send HTTP GET request.
   *
   * @param string $url
   *   URL to send the request to.
   * @param \Symfony\Component\Console\Output\Output $output
   *   Console output interface.
   * @param string $access_token
   *   Access token for API.
   *
   * @return \GuzzleHttp\Psr7\Response
   *   HTTP Response.
   */
  protected function doRequest(string $url, Output $output, string $access_token): Response {
    $response = $this->client->request('GET', $url,
    [
      'http_errors' => FALSE,
      'headers' => [
        'Authorization' => 'Bearer ' . $access_token,
      ],
      'on_stats' => function (TransferStats $stats) use ($output) {
        $output->writeln('Took: ' . $stats->getTransferTime() . ' seconds.');
      },
    ]);

    return $response;
  }

  /**
   * Get API access token from Drupal.
   *
   * @param string $api_key
   *   API key to authenticate with.
   *
   * @return string|null
   *   Access token or NULL if it can't be fetched.
   */
  protected function getAccessToken(string $api_key): ?string {
    $response = $this->client->request('GET', self::TOKEN_URL,
    [
      'http_errors' => FALSE,
      'headers' => [
        'api-key' => $api_key,
      ],
    ]);

    if ($response->getStatusCode() !== 200) {
      return NULL;
    }

    $content = (string) $response->getBody();
    return $content;
  }

}
