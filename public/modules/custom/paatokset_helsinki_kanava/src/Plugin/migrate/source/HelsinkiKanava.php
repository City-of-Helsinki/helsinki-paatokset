<?php

namespace Drupal\paatokset_helsinki_kanava\Plugin\migrate\source;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use Drupal\migrate\Plugin\MigrationInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Source plugin for retrieving Helsinki Kanava videos.
 *
 * @MigrateSource(
 *  id = "helsinki_kanava"
 * )
 */
final class HelsinkiKanava extends SourcePluginBase implements ContainerFactoryPluginInterface {
  /**
   * The total count.
   *
   * @var int
   */
  protected int $count = 0;

  /**
   * The http client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected ClientInterface $httpClient;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ?MigrationInterface $migration = NULL,
  ) {
    if ($url = self::getApiUrl($configuration['url'])) {
      $configuration['url'] = $url;
    }

    $instance = new static($configuration, $plugin_id, $plugin_definition, $migration);
    $instance->httpClient = $container->get('http_client');
    if (!isset($configuration['ids'])) {
      throw new \InvalidArgumentException('The "ids" configuration is missing.');
    }

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function __toString() {
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
  protected static function getApiUrl(string $base_url): ?string {
    $council_id = \Drupal::config('paatokset_helsinki_kanava.settings')->get('city_council_id');

    /** @var \Drupal\paatokset_ahjo_api\Service\MeetingService $meetingsService */
    $meetingsService = \Drupal::service('paatokset_ahjo_meetings');
    $fromTime = strtotime('-4 months') * 1000;
    $nextMeetingDate = $meetingsService->nextMeetingDate($council_id);
    $toTime = $nextMeetingDate ? ((int) $nextMeetingDate) * 1000 : round(microtime(TRUE) * 1000);

    $version = '01';
    $languageId = 'fi_FI';
    $tokenTime = dechex(time());

    $organizationId = getenv('HELSINKI_KANAVA_ID');
    $secret = getenv('HELSINKI_KANAVA_SECRET');

    if (!$organizationId || !$secret) {
      \Drupal::logger('HelsinkiKanava')->warning('Helsinki Kanava credentials are not set. Cannot import the latest council meeting recording.');
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
    $content = $this->getContent($this->configuration['url']);

    foreach ($content as $object) {
      yield $object;
    }
  }

}
