<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset\Kernel\Lupapiste;

use Drupal\KernelTests\KernelTestBase;
use Drupal\paatokset\Lupapiste\DTO\Item;
use Drupal\paatokset\Lupapiste\ItemsStorage;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use GuzzleHttp\Psr7\Response;

/**
 * Tests items storage.
 *
 * @coversDefaultClass \Drupal\paatokset\Lupapiste\ItemsStorage
 */
class ItemsStorageTest extends KernelTestBase {

  use ApiTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'serialization',
    'paatokset',
  ];

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
    $storage = $this->container->get(ItemsStorage::class);
    $this->assertEmpty($storage->load('fi')->items);
    $this->assertEmpty($storage->load('nonexistent')->items);

    $this->assertContainsOnlyInstancesOf(Item::class, $storage->load('fi')->items);
    $this->assertEmpty($storage->load('nonexistent')->items);
  }

}
