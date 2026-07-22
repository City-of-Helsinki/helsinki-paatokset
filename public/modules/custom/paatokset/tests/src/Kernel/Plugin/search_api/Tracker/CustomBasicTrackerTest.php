<?php

namespace Drupal\Tests\paatokset\Kernel\search_api\Tracker;

use Drupal\KernelTests\KernelTestBase;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Utility\Utility;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the customized tracker plugin.
 *
 * @group search_api
 *
 * @coversDefaultClass \Drupal\search_api\Plugin\search_api\tracker\Basic
 */
#[RunTestsInSeparateProcesses]
class CustomBasicTrackerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'search_api',
    'system',
  ];

  /**
   * The tracker plugin used for this test.
   *
   * @var \Drupal\search_api\Plugin\search_api\tracker\Basic
   */
  protected $tracker;

  /**
   * The search index used for this test.
   *
   * @var \Drupal\search_api\IndexInterface
   */
  protected $index;

  /**
   * The test time service used for this test.
   *
   * @var \Drupal\Tests\search_api\Kernel\TestTimeService
   */
  protected $timeService;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installSchema('search_api', ['search_api_item']);
    $this->installConfig('search_api');

    $this->index = Index::create([
      'id' => 'index',
      'tracker_settings' => [
        'custom_basic_tracker' => [],
      ],
    ]);
    $this->tracker = $this->index->getTrackerInstance();
    $this->timeService = new TestTimeService();
    $this->tracker->setTimeService($this->timeService);
  }

  /**
   * Tests tracking.
   *
   * @param string $indexing_order
   *   The indexing order setting to use – "fifo" or "lifo".
   */
  public function testTracking() {
    $this->tracker->setConfiguration(['indexing_order' => 'FIFO']);
    $datasource_1 = 'test1';

    $ids = [];
    foreach ([1, 2, 3] as $raw_id) {
      $ids[0][] = Utility::createCombinedId('test1', $raw_id);
    }

    // Insert items.
    /*
    $this->tracker->trackItemsInserted([$ids[0][0]]);
    $this->timeService->advanceTime();
    $this->tracker->trackItemsInserted([$ids[0][1], $ids[0][2]]);
    $this->timeService->advanceTime();
    */
    // Make sure the remaining items are returned as expected.
    $to_index = $this->tracker->getRemainingItems(4);



    /*
    sort($to_index);

      $expected = [$ids[0][0], $ids[0][2], $ids[1][0], $ids[1][1]];

    $this->assertEquals($expected, $to_index);

    $to_index = $this->tracker->getRemainingItems(1, $datasource_1);

      $expected = [$ids[0][0]];

    $this->assertEquals($expected, $to_index);

    $to_index = $this->tracker->getRemainingItems(-1);
    sort($to_index);
    $expected = array_merge($ids[0], $ids[1]);
    $this->assertEquals($expected, $to_index);

    $to_index = $this->tracker->getRemainingItems(-1, $datasource_2);
    sort($to_index);
    $this->assertEquals($ids[1], $to_index);

    // Make sure that tracking an unindexed item as updated will not affect its
    // position for FIFO, but will get it to the front for LIFO. (If we do this
    // with the item that's in front for FIFO anyways, we can use the same code
    // in both cases.)
    $this->tracker->trackItemsUpdated([$ids[0][0]]);
    $this->timeService->advanceTime();
    $to_index = $this->tracker->getRemainingItems(1, $datasource_1);
    $this->assertEquals([$ids[0][0]], $to_index);

    // Make sure calling methods with an empty $ids array doesn't blow anything
    // up.
    $this->tracker->trackItemsInserted([]);
    $this->tracker->trackItemsUpdated([]);
    $this->tracker->trackItemsIndexed([]);
    $this->tracker->trackItemsDeleted([]);

    // None of this should have changed the indexing status of any items.
    $this->assertIndexingStatus(0, 6);
    $this->assertIndexingStatus(0, 3, $datasource_1);
    $this->assertIndexingStatus(0, 3, $datasource_2);

    // Now, change the status of some of the items.
    $this->tracker->trackItemsIndexed([$ids[0][0], $ids[0][1], $ids[1][0]]);
    $this->assertIndexingStatus(3, 6);
    $this->assertIndexingStatus(2, 3, $datasource_1);
    $this->assertIndexingStatus(1, 3, $datasource_2);
    $to_index = $this->tracker->getRemainingItems(-1);
    sort($to_index);
    $expected = [$ids[0][2], $ids[1][1], $ids[1][2]];
    $this->assertEquals($expected, $to_index);

    $this->tracker->trackItemsUpdated([$ids[0][0], $ids[0][2]]);
    $this->timeService->advanceTime();
    $this->assertIndexingStatus(2, 6);
    $this->assertIndexingStatus(1, 3, $datasource_1);
    $this->assertIndexingStatus(1, 3, $datasource_2);
    $to_index = $this->tracker->getRemainingItems(-1);
    sort($to_index);
    array_unshift($expected, $ids[0][0]);
    $this->assertEquals($expected, $to_index);

    $this->tracker->trackItemsDeleted([$ids[1][0], $ids[1][2]]);
    $this->assertIndexingStatus(1, 4);
    $this->assertIndexingStatus(1, 3, $datasource_1);
    $this->assertIndexingStatus(0, 1, $datasource_2);
    $to_index = $this->tracker->getRemainingItems(-1);
    sort($to_index);
    // The last element of $expected is $ids[1][2], which we just deleted.
    unset($expected[3]);
    $this->assertEquals($expected, $to_index);


      // These are the only two (remaining) items that were never indexed, so
      // they still have their original insert time stamp and thus go first.
      $expected = [$ids[0][2], $ids[1][1]];

    $to_index = $this->tracker->getRemainingItems(2);
    sort($to_index);
    $this->assertEquals($expected, $to_index);

    // Some more status changes.
    $this->tracker->trackItemsInserted([$ids[1][2]]);
    $this->timeService->advanceTime();
    $this->assertIndexingStatus(1, 5);
    $this->assertIndexingStatus(1, 3, $datasource_1);
    $this->assertIndexingStatus(0, 2, $datasource_2);

    $this->tracker->trackItemsIndexed(array_merge($ids[0], $ids[1]));
    $this->assertIndexingStatus(5, 5);
    $this->assertIndexingStatus(3, 3, $datasource_1);
    $this->assertIndexingStatus(2, 2, $datasource_2);

    $this->tracker->trackAllItemsUpdated($datasource_1);
    $this->timeService->advanceTime();
    $this->assertIndexingStatus(2, 5);
    $this->assertIndexingStatus(0, 3, $datasource_1);
    $this->assertIndexingStatus(2, 2, $datasource_2);

    $this->tracker->trackItemsIndexed([$ids[0][0]]);
    $this->assertIndexingStatus(3, 5);
    $this->assertIndexingStatus(1, 3, $datasource_1);
    $this->assertIndexingStatus(2, 2, $datasource_2);

    $this->tracker->trackAllItemsUpdated();
    $this->timeService->advanceTime();
    $this->assertIndexingStatus(0, 5);
    $this->assertIndexingStatus(0, 3, $datasource_1);
    $this->assertIndexingStatus(0, 2, $datasource_2);

    $this->tracker->trackAllItemsDeleted($datasource_2);
    $this->assertIndexingStatus(0, 3);
    $this->assertIndexingStatus(0, 3, $datasource_1);
    $this->assertIndexingStatus(0, 0, $datasource_2);

    $this->tracker->trackAllItemsDeleted();
    $this->assertIndexingStatus(0, 0);
    $this->assertIndexingStatus(0, 0, $datasource_1);
    $this->assertIndexingStatus(0, 0, $datasource_2);
    */
  }

}
