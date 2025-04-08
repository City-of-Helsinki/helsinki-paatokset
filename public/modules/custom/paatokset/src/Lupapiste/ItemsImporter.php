<?php

declare(strict_types=1);

namespace Drupal\paatokset\Lupapiste;

use Drupal\paatokset\Lupapiste\DTO\Item;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Laminas\Feed\Exception\InvalidArgumentException;
use Laminas\Feed\Reader\Feed\AbstractFeed;
use Laminas\Feed\Reader\Reader;

/**
 * Import items from Lupapiste RSS.
 */
final class ItemsImporter {

  public function __construct(
    private ClientInterface $httpClient,
  ) {
  }

  /**
   * Gets the RSS uri for given language.
   *
   * @param string $langcode
   *   The langcode.
   *
   * @return string
   *   The uri.
   */
  public function getUri(string $langcode): string {
    return sprintf('https://kuulutukset.lupapiste.fi/rss/kuulutus?organization=091-R&lang=%s', $langcode);
  }

  /**
   * Fetches the RSS items.
   *
   * @param string $langcode
   *   The langcode.
   *
   * @return array
   *   The data.
   */
  public function fetch(string $langcode) : array {
    try {
      $data = $this->httpClient->request('GET', $this->getUri($langcode))
        ->getBody()
        ->getContents();
      $feed = Reader::importString($data);
    }
    catch (GuzzleException | InvalidArgumentException) {
      return [];
    }
    assert($feed instanceof AbstractFeed);

    $feed->getXpath()->registerNamespace('lupapiste', 'https://www.lupapiste.fi/rss/extensions');

    $evaluateXpath = function (string $field, string $prefix) use ($feed) : string {
      if ($value = $feed->getXpath()->evaluate(sprintf('string(%s/%s)', $prefix, $field))) {
        return $value;
      }
      return $feed->getXpath()->evaluate(sprintf('string(%s/lupapiste:%s)', $prefix, $field));
    };

    $items = [];
    $keys = get_class_vars(Item::class);

    foreach ($feed as $delta => $item) {
      if (!is_callable([$item, 'getXpathPrefix'], TRUE)) {
        throw new \LogicException('Missing getXpathPrefix() method');
      }
      foreach ($keys as $k => $v) {
        $items[$delta][$k] = $evaluateXpath($k, $item->getXpathPrefix());
      }
    }
    return $items;
  }

}
