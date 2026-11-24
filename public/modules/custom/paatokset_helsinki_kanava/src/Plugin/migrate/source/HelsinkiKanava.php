<?php

declare(strict_types=1);

namespace Drupal\paatokset_helsinki_kanava\Plugin\migrate\source;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\migrate\Attribute\MigrateSource;
use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\paatokset_helsinki_kanava\CouncilMeetings;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Source plugin for retrieving Helsinki Kanava videos.
 */
#[MigrateSource(
  id: 'helsinki_kanava'
)]
final class HelsinkiKanava extends SourcePluginBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a new instance.
   *
   * @phpstan-param array<string, mixed> $configuration
   * @phpstan-param string $plugin_id
   * @phpstan-param mixed $plugin_definition
   *
   * @throws \Drupal\migrate\MigrateException
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    MigrationInterface $migration,
    protected readonly ClientInterface $httpClient,
    protected readonly CouncilMeetings $councilMeetings,
    protected readonly LoggerInterface $logger,
  ) {
    if (!isset($configuration['ids'])) {
      throw new \InvalidArgumentException('The "ids" configuration is missing.');
    }

    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ?MigrationInterface $migration = NULL,
  ): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get(ClientInterface::class),
      $container->get(CouncilMeetings::class),
      $container->get('logger.channel.paatokset_helsinki_kanava'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __toString(): string {
    return 'HelsinkiKanava';
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return $this->configuration['ids'];
  }

  /**
   * {@inheritdoc}
   */
  public function fields(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function count($refresh = FALSE): int {
    return -1;
  }

  /**
   * Sends a HTTP request and returns response data as array.
   *
   * @param string $url
   *   The url.
   *
   * @return array
   *   The XML returned by API service.
   */
  protected function getContent(string $url) : array {
    try {
      $response = (string) $this->httpClient->request('GET', $url)->getBody();

      if (!is_null($response)) {
        $content = json_decode($response, TRUE);
        $events = $content['events'];

        return $events;
      }
    }
    catch (GuzzleException $e) {
    }
    return [];
  }

  /**
   * Gets modified API Url with correct query parameters for fetching videos.
   *
   * @param string $base_url
   *   Base URL for the service.
   *
   * @return string|null
   *   Updated URL with correct parameters or NULL if dependencies are missing.
   */
  protected function getApiUrl(string $base_url): ?string {
    $fromTime = strtotime('-4 months') * 1000;
    $nextMeetingTimestamp = $this->councilMeetings->nextMeetingTimestamp();
    $toTime = $nextMeetingTimestamp ? $nextMeetingTimestamp * 1000 : round(microtime(TRUE) * 1000);

    $version = '01';
    $languageId = 'fi_FI';
    $tokenTime = dechex(time());

    $organizationId = getenv('HELSINKI_KANAVA_ID');
    $secret = getenv('HELSINKI_KANAVA_SECRET');

    if (!$organizationId || !$secret) {
      $this->logger->warning('Helsinki Kanava credentials are not set. Cannot import the latest council meeting recording.');
      return NULL;
    }

    $hash = md5(
      $organizationId . ':' .
      $fromTime . ':' .
      $toTime . ':' .
      $languageId . ':' .
      $tokenTime . ':' .
      $secret
    );

    $token = $version . $tokenTime . $hash;

    $url = Url::fromUri($base_url, [
      'query' => [
        'action' => 'getUpcomingEvents',
        'organizationId' => $organizationId,
        'languageId' => $languageId,
        'filter' => 'eventcode',
        'begin' => '0',
        'end' => '1',
        'version' => $version,
        'from' => $fromTime,
        'to' => $toTime,
        'token' => $token,
      ],
    ]);

    return $url->toString();
  }

  /**
   * {@inheritdoc}
   */
  protected function initializeIterator() : \Iterator {
    if (!$url = $this->getApiUrl($this->configuration['url'])) {
      return;
    }

    $content = $this->getContent($url);

    foreach ($content as $object) {
      yield $object;
    }
  }

}
