<?php

declare(strict_types=1);

namespace Drupal\paatokset\Lupapiste;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\paatokset\Lupapiste\DTO\Collection;
use Drupal\paatokset\Lupapiste\DTO\Item;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Item storage.
 */
final readonly class ItemsStorage {

  public const PER_PAGE = 10;
  public const CACHE_KEY = 'paatokset.lupapiste_data';
  public const CACHE_TAG = 'paatokset.lupapiste_data';
  public const LAST_FETCH_TIMESTAMP = 'paatokset.lupapiste_rss_last_fetch';
  public const LAST_PUBDATE_TIMESTAMP = 'paatokset.lupapiste_rss_last_pubdate';

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\paatokset\Lupapiste\ItemsImporter $importer
   *   The importer.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache.
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cacheTagsInvalidator
   *   The cache tags invalidator.
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
   *   The serializer.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time.
   */
  public function __construct(
    private ItemsImporter $importer,
    #[Autowire('@cache.default')] private CacheBackendInterface $cache,
    #[Autowire('@cache_tags.invalidator')] private CacheTagsInvalidatorInterface $cacheTagsInvalidator,
    #[Autowire('@serializer')] private SerializerInterface $serializer,
    #[Autowire('@state')] private StateInterface $state,
    #[Autowire('@datetime.time')] private TimeInterface $time,
  ) {
  }

  /**
   * Deserialized the given JSON data.
   *
   * @param string $data
   *   The JSON data to deserialize.
   *
   * @return \Drupal\paatokset\Lupapiste\DTO\Item[]
   *   The deserialized data.
   */
  private function deserialize(string $data) : array {
    return $this->serializer->deserialize($data, Item::class . '[]', 'json');
  }

  /**
   * Get current RSS feed published timestamp.
   *
   * @return int
   *   The timestamp.
   */
  private function getCurrentPublishedTimestamp() : int {
    $data = $this->importer->fetch('fi');
    $pubDateTimestamp = $data['pubDate'] ? strtotime($data['pubDate']) : 0;
    return $pubDateTimestamp;
  }

  /**
   * Gets the data.
   *
   * @param string $langcode
   *   The langcode.
   *
   * @return array
   *   The data.
   */
  private function getData(string $langcode) : array {
    $from_cache = $this->cache->get(self::CACHE_KEY);
    if ($from_cache && isset($from_cache->data[$langcode])) {
      return $from_cache->data[$langcode];
    }

    $data = $this->importer->fetch($langcode);
    $items = $data['items'] ?? [];
    $pubDate = isset($data['pubDate']) ? strtotime($data['pubDate']) : 0;

    // Save fetched items to cache.
    $to_cache = $from_cache ? $from_cache->data : [];
    $to_cache[$langcode] = $items;
    $this->cache->set(self::CACHE_KEY, $to_cache);

    // Save timestamps for cache clearing.
    $this->state->set(self::LAST_FETCH_TIMESTAMP, $this->time->getRequestTime());
    if ($pubDate) {
      $this->state->set(self::LAST_PUBDATE_TIMESTAMP, $pubDate);
    }

    return $items;
  }

  /**
   * Loads the Lupapiste data for given language.
   *
   * @param string $langcode
   *   The language.
   * @param int $offset
   *   The offset to load.
   * @param int $length
   *   The maximum number of items to load.
   *
   * @return \Drupal\paatokset\Lupapiste\DTO\Collection
   *   The data.
   */
  public function load(string $langcode, int $offset = 0, int $length = self::PER_PAGE) : Collection {
    $data = $this->getData($langcode);
    $total = count($data);

    if ($length > 0) {
      $data = array_slice($data, $offset, $length);
    }
    return new Collection(
      total: $total,
      items: $this->deserialize(json_encode($data)),
      url: Url::fromUri($this->importer->getUri($langcode)),
    );
  }

  /**
   * Purge cache if needed.
   *
   * @param int $max_age_seconds
   *   The maximum age of the cache in seconds. Defaults to 24 hours.
   *
   * @return bool
   *   TRUE if cache was purged, FALSE otherwise.
   */
  public function purgeCacheIfNeeded(int $max_age_seconds = 86400) : bool {
    $last_fetched = $this->state->get(self::LAST_FETCH_TIMESTAMP, 0);
    $last_pubdate = $this->state->get(self::LAST_PUBDATE_TIMESTAMP, 0);

    // Purge cache if it's older than max age or if the RSS feed has updated.
    if (
      $last_fetched < $this->time->getRequestTime() - $max_age_seconds
      || $last_pubdate < $this->getCurrentPublishedTimestamp()
    ) {
      $this->cache->delete(self::CACHE_KEY);
      $this->cacheTagsInvalidator->invalidateTags([self::CACHE_TAG]);

      return TRUE;
    }

    return FALSE;
  }

}
