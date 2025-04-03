<?php

declare(strict_types=1);

namespace Drupal\paatokset\Lupapiste;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\paatokset\Lupapiste\DTO\Collection;
use Drupal\paatokset\Lupapiste\DTO\Item;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Item storage.
 */
final readonly class ItemsStorage {

  public const PER_PAGE = 10;

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\paatokset\Lupapiste\ItemsImporter $importer
   *   The importer.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache.
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
   *   The serializer.
   */
  public function __construct(
    private ItemsImporter $importer,
    #[Autowire('@cache.default')] private CacheBackendInterface $cache,
    #[Autowire('@serializer')] private SerializerInterface $serializer,
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
   * Gets the data.
   *
   * @param string $langcode
   *   The langcode.
   *
   * @return array
   *   The data.
   */
  private function getData(string $langcode) : array {
    $key = sprintf('lupapiste_data_%s', $langcode);

    if ($data = $this->cache->get($key)) {
      return $data->data;
    }
    $items = $this->importer->fetch($langcode);
    $this->cache->set($key, $items);

    return $items;
  }

  /**
   * Loads the data for given language.
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
    );
  }

}
