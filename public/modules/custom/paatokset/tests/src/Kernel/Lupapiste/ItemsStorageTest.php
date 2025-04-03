<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset\Kernel\Lupapiste;

use Drupal\KernelTests\KernelTestBase;
use Drupal\paatokset\Lupapiste\DTO\Item;
use Drupal\paatokset\Lupapiste\ItemsStorage;

/**
 * Tests items storage.
 *
 * @coversDefaultClass \Drupal\paatokset\Lupapiste\ItemsStorage
 */
class ItemsStorageTest extends KernelTestBase {

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
    $storage = $this->container->get(ItemsStorage::class);
    $this->assertEmpty($storage->load('fi'));
    $this->assertEmpty($storage->load('nonexistent'));

    $this->assertContainsOnlyInstancesOf(Item::class, $storage->load('fi'));
    $this->assertEmpty($storage->load('nonexistent'));
  }

}
