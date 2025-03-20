<?php

declare(strict_types=1);

namespace Drupal\paatokset_rss;

use Drupal\paatokset_rss\DTO\LupapisteItem;
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
    $uri = sprintf('https://kuulutukset-qa.lupapiste.fi/rss/kuulutus?organization=049-R&lang=%s', $langcode);

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

    $evaluateXpath = function (string $field, string $prefix) use ($feed) {
      if ($value = $feed->getXpath()->evaluate(sprintf('string(%s/%s)', $prefix, $field))) {
        return $value;
      }
      return $feed->getXpath()->evaluate(sprintf('string(%s/lupapiste:%s)', $prefix, $field));
    };

    $items = [];

    $keys = get_class_vars(LupapisteItem::class);

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
