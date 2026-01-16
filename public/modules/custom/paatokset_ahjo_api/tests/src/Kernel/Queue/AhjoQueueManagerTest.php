<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\Queue;

use Drupal\Core\Database\Connection;
use Drupal\Core\Queue\QueueFactory;
use Drupal\paatokset_ahjo_api\Queue\AhjoQueue;
use Drupal\paatokset_ahjo_api\Queue\AhjoQueueManager;
use Drupal\paatokset_ahjo_api\Queue\Item;
use Drupal\Tests\paatokset_ahjo_api\Kernel\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests AhjoQueueManager service.
 */
#[Group('paatokset_ahjo_api')]
class AhjoQueueManagerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
  ];

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create the queue table by accessing the queue.
    // The DatabaseQueue creates the table lazily on first use.
    $queue = $this->container
      ->get(QueueFactory::class)
      ->get(AhjoQueue::AggregationQueue->value);

    $queue->createQueue();

    // Database queues are created lazily on first use.
    // createQueue is a no-op. We need to ensure that the
    // table exists since the queue service reads the table
    // before writing to it.
    $queue->createItem([]);
    $queue->claimItem();
  }

  /**
   * Tests adding item to queue.
   */
  public function testAddItemToQueue(): void {
    $sut = new AhjoQueueManager(
      $this->container->get(QueueFactory::class),
      $this->container->get(Connection::class),
    );

    // Adding item to queue should return item ID.
    $result = $sut->addItemToAhjoQueue(
      AhjoQueue::AggregationQueue,
      'HEL-2025-000001',
      'ahjo_cases_v2'
    );

    $this->assertNotNull($result);
    $this->assertNotFalse($result);

    // Verify item was added to the queue.
    $queue = $this->container->get(QueueFactory::class)->get(AhjoQueue::AggregationQueue->value);
    $item = $queue->claimItem();
    $this->assertIsObject($item);
    assert($item instanceof \stdClass);
    $this->assertEquals('v2', $item->data['id']);
    $this->assertInstanceOf(Item::class, $item->data['content']);
    $this->assertEquals('HEL-2025-000001', $item->data['content']->id);
  }

  /**
   * Tests that duplicate items are not added to queue.
   */
  public function testDuplicateItemsAreNotAdded(): void {
    $sut = new AhjoQueueManager(
      $this->container->get(QueueFactory::class),
      $this->container->get(Connection::class),
    );

    // First item should be added.
    $result1 = $sut->addItemToAhjoQueue(
      AhjoQueue::AggregationQueue,
      'HEL-2025-000002',
      'ahjo_cases_v2'
    );
    $this->assertNotNull($result1);

    // Second item with same ID should return NULL (duplicate).
    $result2 = $sut->addItemToAhjoQueue(
      AhjoQueue::AggregationQueue,
      'HEL-2025-000002',
      'ahjo_cases_v2'
    );
    $this->assertNull($result2);
  }

}
