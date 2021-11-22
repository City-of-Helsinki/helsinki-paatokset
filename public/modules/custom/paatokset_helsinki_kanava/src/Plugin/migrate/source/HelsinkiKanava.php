<?php

namespace Drupal\paatokset_helsinki_kanava\Plugin\migrate\source;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use Drupal\migrate\Row;
use Drupal\node\Entity\Node;
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
class HelsinkiKanava extends SourcePluginBase implements ContainerFactoryPluginInterface {
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
  public function fields() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $recordings = $row->getSourceProperty('recordings');

    if ($recordings && !(empty($recordings))) {
      foreach ($recordings as $record) {
        if (isset($record['assetId'])) {
          $row->setSourceProperty('assetId', $record['assetId']);
          return;
        }
      }
    }
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
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
    MigrationInterface $migration = NULL
  ) {
    $councilId = \Drupal::config('paatokset_helsinki_kanava.settings')->get('city_council_node');
    $council = Node::load($councilId);

    if (!$council) {
      \Drupal::logger('HelsinkiKanava')->warning('Council node can\'t be found. Cannot import the latest council meeting recording.');
      return;
    }

    $councilUri = $council->get('field_resource_uri')->value;

    $database = \Drupal::database();
    $query = $database->select('paatokset_meeting_field_data', 'pmfd')
      ->fields('pmfd', ['meeting_date'])
      // Use the second most recent meeting as from-time.
      ->range(1, 2)
      ->condition('pmfd.policymaker_uri', $councilUri)
      ->orderBy('meeting_date', 'DESC');
    $result = $query->execute()->fetch();

    if (!isset($result->meeting_date)) {
      \Drupal::logger('HelsinkiKanava')->warning('Latest council meeting not found. Cannot import the latest council meeting recording.');
      return;
    }

    $meetingsService = \Drupal::service('Drupal\paatokset_ahjo_api\Service\MeetingService');
    $nextMeetingDate = $meetingsService->nextMeetingDate($council->get('title')->value);
    $toTime = $nextMeetingDate ? $nextMeetingDate * 1000 : round(microtime(TRUE) * 1000);

    $version = '01';
    $languageId = 'fi_FI';
    $dateTime = new \DateTime($result->meeting_date);
    $fromTime = strtotime($result->meeting_date) * 1000;
    $tokenTime = dechex(time());

    $organizationId = \Drupal::config('paatokset_helsinki_kanava.settings')->get('helsinki_kanava_id');
    $secret = \Drupal::config('paatokset_helsinki_kanava.settings')->get('helsinki_kanava_secret');

    if (!$organizationId || !$secret) {
      \Drupal::logger('HelsinkiKanava')->warning('Helsinki Kanava credentials are not set. Cannot import the latest council meeting recording.');
      return;
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

    $base_url = $configuration['url'];
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
    $configuration['url'] = $url->toString();

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
  protected function initializeIterator() : \Iterator {
    $content = $this->getContent($this->configuration['url']);

    foreach ($content as $object) {
      yield $object;
    }
  }

}
