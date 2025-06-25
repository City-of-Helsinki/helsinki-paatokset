<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset\Kernel\Lupapiste;

use Drupal\KernelTests\KernelTestBase;
use Drupal\paatokset\Lupapiste\DTO\Item;
use Drupal\paatokset\Lupapiste\ItemsImporter;
use Drupal\paatokset\Lupapiste\ItemsStorage;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use GuzzleHttp\Psr7\Response;
use Drupal\Core\State\StateInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;

/**
 * Tests items storage.
 *
 * @coversDefaultClass \Drupal\paatokset\Lupapiste\ItemsStorage
 */
class ItemsStorageTest extends KernelTestBase {

  use ApiTestTrait;

  protected const CACHE_MAX_AGE = 86400;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'serialization',
    'paatokset',
  ];

  /**
   * State service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Cache mock.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $cache;

  /**
   * Cache tags invalidator mock.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $cacheTagsInvalidator;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->state = $this->container->get(StateInterface::class);
    $this->time = $this->container->get(TimeInterface::class);

    $this->cache = $this->prophesize(CacheBackendInterface::class);
    $this->cacheTagsInvalidator = $this->prophesize(CacheTagsInvalidatorInterface::class);
    $this->container->set('cache.default', $this->cache->reveal());
    $this->container->set('cache_tags.invalidator', $this->cacheTagsInvalidator->reveal());
  }

  /**
   * Make sure we can load and save data.
   */
  public function testCrud(): void {
    $this->container->set('http_client', $this->setupMockHttpClient([
      new Response(),
      new Response(),
      new Response(body: $this->getFixture('paatokset', 'rss_fi.xml')),
      new Response(),
    ]));
    $itemStorage = $this->container->get(ItemsStorage::class);

    $this->assertEmpty($itemStorage->load('fi')->items);
    $this->assertEmpty($itemStorage->load('nonexistent')->items);
    $this->assertContainsOnlyInstancesOf(Item::class, $itemStorage->load('fi')->items);
    $this->assertEmpty($itemStorage->load('nonexistent')->items);
  }

  /**
   * Test cache is not purged when conditions are not met.
   */
  public function testPurgeCacheNotTriggered(): void {
    $this->container->set('http_client', $this->setupMockHttpClient([
      new Response(body: $this->getFixture('paatokset', 'rss_fi.xml')),
    ]));

    // Last fetched timestamp is less than threshold, and latest published
    // date is the same as latest feed; should not clear cache.
    $this->state->set(ItemsStorage::LAST_FETCH_TIMESTAMP, $this->time->getRequestTime() - self::CACHE_MAX_AGE + 1);
    $this->state->set(ItemsStorage::LAST_PUBDATE_TIMESTAMP, strtotime('Wed, 12 Mar 2025 08:54:15 +0200'));

    $this->cache->delete(ItemsStorage::CACHE_KEY)->shouldNotBeCalled();
    $this->cacheTagsInvalidator->invalidateTags([ItemsStorage::CACHE_TAG])->shouldNotBeCalled();

    $itemStorage = $this->container->get(ItemsStorage::class);
    $is_cleared = $itemStorage->purgeCacheIfNeeded(self::CACHE_MAX_AGE);
    $this->assertFalse($is_cleared);
  }

  /**
   * Test cache is purged when last fetched timestamp is older than max age.
   */
  public function testPurgeCacheTriggeredByAge(): void {
    $this->container->set('http_client', $this->setupMockHttpClient([
      new Response(body: $this->getFixture('paatokset', 'rss_fi.xml')),
    ]));

    $time_now = $this->time->getRequestTime();
    $published_timestamp = strtotime('Wed, 12 Mar 2025 08:54:15 +0200');
    $this->state->set(ItemsStorage::LAST_FETCH_TIMESTAMP, $time_now - self::CACHE_MAX_AGE - 1);
    $this->state->set(ItemsStorage::LAST_PUBDATE_TIMESTAMP, $published_timestamp);

    $this->cache->delete(ItemsStorage::CACHE_KEY)->shouldBeCalled();
    $this->cacheTagsInvalidator->invalidateTags([ItemsStorage::CACHE_TAG])->shouldBeCalled();

    $itemStorage = $this->container->get(ItemsStorage::class);
    $is_cleared = $itemStorage->purgeCacheIfNeeded(self::CACHE_MAX_AGE);
    $this->assertTrue($is_cleared);
  }

  /**
   * Test cache is purged when RSS feed has updated.
   */
  public function testPurgeCacheTriggeredByRssUpdate(): void {
    $this->container->set('http_client', $this->setupMockHttpClient([
      new Response(body: $this->getFixture('paatokset', 'rss_fi.xml')),
    ]));

    $time_now = $this->time->getRequestTime();
    $published_timestamp = strtotime('Wed, 12 Mar 2025 08:54:15 +0200');
    $this->state->set(ItemsStorage::LAST_FETCH_TIMESTAMP, $time_now - self::CACHE_MAX_AGE + 1);
    $this->state->set(ItemsStorage::LAST_PUBDATE_TIMESTAMP, $published_timestamp - 1);

    $this->cache->delete(ItemsStorage::CACHE_KEY)->shouldBeCalled();
    $this->cacheTagsInvalidator->invalidateTags([ItemsStorage::CACHE_TAG])->shouldBeCalled();

    $itemStorage = $this->container->get(ItemsStorage::class);
    $is_cleared = $itemStorage->purgeCacheIfNeeded(self::CACHE_MAX_AGE);
    $this->assertTrue($is_cleared);
  }

  /**
   * Test deserialize method.
   */
  public function testDeserialize(): void {
    $httpClient = $this->createMockHttpClient([
      new Response(body: $this->getFixture('paatokset', 'rss_fi.xml')),
    ]);
    $importer = new ItemsImporter($httpClient);
    $data = $importer->fetch('fi');

    /** @var \Drupal\paatokset\Lupapiste\ItemsStorage $storage */
    $storage = $this->container->get(ItemsStorage::class);
    $items = $storage->deserialize(json_encode($data['items']));

    $this->assertContainsOnlyInstancesOf(Item::class, $items);
    $publicationStart = new \DateTime('2025-03-13 00:00:00', new \DateTimeZone('+2'));
    $publicationEnd = new \DateTime('2025-04-22 23:59:59', new \DateTimeZone('+3'));
    $this->assertEquals($publicationStart, $items[0]->julkaisuAlkaa);
    $this->assertEquals($publicationEnd, $items[0]->julkaisuPaattyy);
    $this->assertEquals('Rakennustarkastaja', $items[0]->paattaja);
    $this->assertEquals('fi Asuinkerrostalon tai rivitalon rakentaminen', $items[0]->title);
  }

}
