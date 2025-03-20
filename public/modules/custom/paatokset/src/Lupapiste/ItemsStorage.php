<?php

declare(strict_types=1);

namespace Drupal\paatokset\Lupapiste;

use Drupal\Core\Cache\Cache;
use Drupal\Core\State\StateInterface;
use Drupal\paatokset\Lupapiste\DTO\Item;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Item storage.
 */
final readonly class ItemsStorage {

  public const CACHE_TAGS = ['paatokset'];

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Core\State\StateInterface $storage
   *   The state API.
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
   *   The serializer.
   */
  public function __construct(
    private StateInterface $storage,
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
   * Gets the storage key for given language.
   *
   * @param string $langcode
   *   The langcode.
   *
   * @return string
   *   The storage key.
   */
  private function getStorageKey(string $langcode) : string {
    return sprintf('lupapiste_data_%s', $langcode);
  }

  /**
   * Saves the given items.
   *
   * @param string $langcode
   *   The langcode.
   * @param array $data
   *   The data.
   */
  public function save(string $langcode, array $data) : void {
    // Deserialize data to make sure it's valid.
    $data = $this->deserialize(json_encode($data));

    $this->storage->set($this->getStorageKey($langcode), $this->serializer->serialize($data, 'json'));
    Cache::invalidateTags(self::CACHE_TAGS);
  }

  /**
   * Loads the data for given language.
   *
   * @param string $langcode
   *   The language.
   *
   * @return \Drupal\paatokset\Lupapiste\DTO\Item[]
   *   The data.
   */
  public function load(string $langcode) : array {
    if (!$data = $this->storage->get($this->getStorageKey($langcode))) {
      return [];
    }
    return $this->deserialize($data);
  }

}
