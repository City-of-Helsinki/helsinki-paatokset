<?php

declare(strict_types=1);

namespace Drupal\paatokset_rss;

use Drupal\Core\Cache\Cache;
use Drupal\Core\State\StateInterface;
use Drupal\paatokset_rss\DTO\LupapisteItem;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Item storage.
 */
final readonly class ItemsStorage {

  public const CACHE_TAGS = ['paatokset_rss'];

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
   * @return \Drupal\paatokset_rss\DTO\LupapisteItem[]
   *   The deserialized data.
   */
  private function deserialize(string $data) : array {
    return $this->serializer->deserialize($data, LupapisteItem::class . '[]', 'json');
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

    $this->storage->set('lupapiste_data_' . $langcode, $this->serializer->serialize($data, 'json'));
    Cache::invalidateTags(self::CACHE_TAGS);
  }

  /**
   * Loads the data for given language.
   *
   * @param string $langcode
   *   The language.
   *
   * @return \Drupal\paatokset_rss\DTO\LupapisteItem[]
   *   The data.
   */
  public function load(string $langcode) : array {
    if (!$data = $this->storage->get('lupapiste_data_' . $langcode)) {
      return [];
    }
    return $this->deserialize($data);
  }

}
