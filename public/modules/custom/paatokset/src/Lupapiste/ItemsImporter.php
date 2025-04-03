<?php

declare(strict_types=1);

namespace Drupal\paatokset\Lupapiste;

use Drupal\paatokset\Lupapiste\DTO\Item;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
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
   * Fetches the RSS items.
   *
   * @param string $langcode
   *   The langcode.
   *
   * @return array
   *   The data.
   */
  public function fetch(string $langcode) : array {
    $uri = sprintf('https://kuulutukset.lupapiste.fi/rss/kuulutus?organization=091-R&lang=%s', $langcode);

    try {
      $data = $this->httpClient->request('GET', $uri)
        ->getBody()
        ->getContents();
    }
    catch (GuzzleException) {
      return [];
    }
    $feed = Reader::importString($data);
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
