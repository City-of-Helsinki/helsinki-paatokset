<?php

namespace Drupal\paatokset_news_importer\Plugin\migrate\source;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use Drupal\migrate\Plugin\MigrationInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Source plugin for retrieving articles from RSS feed.
 *
 * @MigrateSource(
 *  id = "imported_article"
 * )
 */
class ImportedArticle extends SourcePluginBase implements ContainerFactoryPluginInterface {
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
    return 'ImportedArticle';
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
      $content = (string) $this->httpClient->request('GET', $url)->getBody();

      if (!is_null($content)) {
        return $this->xmlToArray($content);
      }

      return $content;
    }
    catch (GuzzleException $e) {
    }
    return [];
  }

  /**
   * Returns items from xml in an array.
   *
   * @param string $xml
   *   XML string.
   *
   * @return array
   *   Transformed XML in array form
   */
  private function xmlToArray($xml) {
    $content = new \SimpleXMLElement($xml);
    $result = [];

    if (isset($content->channel->item)) {
      foreach ($content->channel->item as $item) {
        $transformedItem = [];
        foreach ($item as $key => $value) {
          if ($key === 'description') {
            // If there is no image, there is extra '/>' in the description.
            $transformedItem['lead'] = preg_replace('/\/>/', '', strip_tags((string) $value));
          }
          $transformedItem[$key] = (string) $value;
        }
        if (isset($transformedItem['description'])) {
          $doc = new \DOMDocument();
          @$doc->loadHTML($transformedItem['description']);
          $images = $doc->getElementsByTagName('img');
          if (count($images) > 0) {
            $transformedItem['image_url'] = $images[0]->getAttribute('src');
            $transformedItem['image_alt'] = utf8_decode($images[0]->getAttribute('alt'));
            $transformedItem['image_title'] = utf8_decode($images[0]->getAttribute('title'));
          }
        }

        $transformedItem['content'] = (string) $item->xpath('content:encoded')[0];
        $result[] = $transformedItem;
      }
    }
    return $result;
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
    $instance = new static($configuration, $plugin_id, $plugin_definition, $migration);
    $instance->httpClient = $container->get('http_client');

    if (!isset($configuration['url'])) {
      throw new \InvalidArgumentException('The "url" configuration is missing.');
    }

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
