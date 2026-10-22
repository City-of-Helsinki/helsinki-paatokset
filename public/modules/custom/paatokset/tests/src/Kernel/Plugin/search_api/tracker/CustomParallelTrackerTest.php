<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset\Kernel\search_api;

use Drupal\paatokset\Plugin\search_api\tracker\CustomParallelTracker;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Utility\Utility;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\paatokset_ahjo_api\Kernel\AhjoEntityKernelTestBase;
use Drupal\Tests\search_api\Kernel\TestTimeService;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the customized parallel tracker plugin.
 */
#[RunTestsInSeparateProcesses]
class CustomParallelTrackerTest extends AhjoEntityKernelTestBase {

  use NodeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'search_api',
    'system',
    'serialization',
    'paatokset',
  ];

  /**
   * The index.
   */
  private IndexInterface $index;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installSchema('search_api', ['search_api_item']);
    $this->installEntitySchema('search_api_task');

    $this->installConfig(['search_api']);

    $this->index = Index::create([
      'id' => 'index',
      'tracker_settings' => [
        'custom_parallel_tracker' => [],
      ],
    ]);
    $timeService = new TestTimeService();
    $tracker = $this->index->getTrackerInstance();
    $this->assertInstanceOf(CustomParallelTracker::class, $tracker);
    $tracker->setTimeService($timeService);

    $nodes = [];
    for ($i = 0; $i <= 2; $i++) {
      $nodes[] = $this->createNode([
        'type' => 'node',
        'title' => $i,
        'field_meeting_id' => "meeting-$i",
        'field_policymaker_id' => "pm-$i",
        'field_diary_number' => "HEL-$i",
      ]);
      $nodes[$i]->save();
      $timeService->advanceTime();
    }

    $this->index->trackItemsInserted(
      'entity',
      array_map(
        fn ($item) => Utility::createCombinedId('entity', (string) $item->id()),
        $nodes
      )
    );
  }

  /**
   * Tests tracking order reversed LIFO.
   */
  public function testTracking(): void {
    $tracker = $this->index->getTrackerInstance();
    $this->assertEquals(3, $tracker->getTotalItemsCount());
    $this->assertEquals('lifo', $tracker->getConfiguration()['indexing_order']);

    // Check that the last created item is first in order.
    $indexingOrder = $tracker->getRemainingItems();
    $this->assertTrue(str_contains($indexingOrder[0], '3'));
    $this->assertTrue(str_contains($indexingOrder[1], '2'));
    $this->assertTrue(str_contains($indexingOrder[2], '1'));
  }

  /**
   * Tests that parallel workers claim different items in order.
   */
  public function testParallelWorkers(): void {
    /** @var \Drupal\search_api\Tracker\TrackerPluginManager $manager */
    $manager = $this->container->get('plugin.manager.search_api.tracker');
    $workers = [];

    foreach ([1, 2] as $key) {
      $worker = $manager->createInstance('custom_parallel_tracker', ['#index' => $this->index]);
      $this->assertInstanceOf(CustomParallelTracker::class, $worker);
      $this->assertTrue($worker->lock());
      $workers[$key] = $worker;
    }

    $first = $workers[1]->getRemainingItems(1);
    $second = $workers[2]->getRemainingItems(1);

    $this->assertCount(1, $first);
    $this->assertCount(1, $second);
    $this->assertTrue(str_contains($first[0], '3'));
    $this->assertTrue(str_contains($second[0], '2'));
    $this->assertEquals(1, $workers[1]->getRemainingItemsCount());

    // Unlocking releases the claimed items.
    $workers[1]->unlock();
    $workers[2]->unlock();
    $this->assertEquals(3, $workers[1]->getRemainingItemsCount());
  }

}
