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
   * Make we can save and load data with missing fields.
   */
  public function testSaveMissingData(): void {
    $storage = $this->container->get(ItemsStorage::class);
    $storage->save('fi', [
      'title' => 'dsadsa',
    ]);
    $this->assertContainsOnlyInstancesOf(Item::class, $storage->load('fi'));
  }

  /**
   * Make sure we can load and save data.
   */
  public function testCrud(): void {
    $storage = $this->container->get(ItemsStorage::class);
    $this->assertEmpty($storage->load('fi'));
    $this->assertEmpty($storage->load('nonexistent'));

    $storage->save('fi', [
      [
        'title' => 'Test title',
        'description' => 'Test Description',
        'link' => 'https://example.com/',
        'pubDate' => 'now',
        'toimenpideteksti' => 'Toimenpidetekst',
        'rakennuspaikka' => 'Testikatu 1',
        'lupatunnus' => 'Lupatunnus',
        'julkaisuAlkaa' => 'now',
        'julkaisuPaattyy' => 'now',
        'kiinteistotunnus' => 'Kiinteistotunnus',
        'paatosPvm' => 'now',
        'paatoksenPykala' => 'Paatoksenpykala',
        'paattaja' => 'Paattaja',
        'asiakirjaLink' => 'https://example.com/',
      ],
    ]);

    $this->assertContainsOnlyInstancesOf(Item::class, $storage->load('fi'));
    $this->assertEmpty($storage->load('nonexistent'));
  }

}
