<?php

namespace Drupal\paatokset_helsinki_kanava\Plugin\migrate\source;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
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
    $councilUri = $council->get('field_resource_uri')->value;

    $database = \Drupal::database();
    $query = $database->select('paatokset_meeting_field_data', 'pmfd')
      ->fields('pmfd', ['meeting_date'])
      ->range(0, 1)
      ->condition('pmfd.policymaker_uri', $councilUri)
      ->orderBy('meeting_date', 'DESC');
    $result = $query->execute()->fetch();

    if (!isset($result->meeting_date)) {
      throw new \Exception('Latest council meeting not found.');
    }

    $version = '01';
    $languageId = 'fi_FI';
    $dateTime = new \DateTime($result->meeting_date);
    $fromTime = strtotime($result->meeting_date) * 1000;
    $toTime = round(microtime(TRUE) * 1000);
    $tokenTime = dechex(time());

    $organizationId = getenv('HELSINKI_KANAVA_ID');
    $secret = getenv('HELSINKI_KANAVA_SECRET');

    if (!$organizationId || !$secret) {
      throw new \Exception('Helsinki Kanava credentials are not set.');
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
