<?php

namespace Drupal\Tests\paatokset\Kernel\search_api;

use Drupal\paatokset\Plugin\search_api\tracker\CustomBasicTracker;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Utility\Utility;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\paatokset_ahjo_api\Kernel\AhjoEntityKernelTestBase;
use Drupal\Tests\search_api\Kernel\TestTimeService;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the customized tracker plugin.
 */
#[RunTestsInSeparateProcesses]
class CustomBasicTrackerTest extends AhjoEntityKernelTestBase {

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
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    // xdebug_break();
    $this->installSchema('search_api', ['search_api_item']);
    $this->installEntitySchema('search_api_task');

    $this->installConfig(['search_api']);
  }

  /**
   * Tests tracking order reversed LIFO.
   */
  public function testTracking(): void {
    $index = Index::create([
      'id' => 'index',
      'tracker_settings' => [
        'custom_basic_tracker' => [],
      ],
    ]);
    $tracker = $index->getTrackerInstance();
    $timeService = new TestTimeService();
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

    $index->trackItemsInserted(
      'entity',
      array_map(
        fn ($item) => Utility::createCombinedId('entity', $item->id()),
        $nodes
      )
    );

    $total = $index->getTrackerInstanceIfAvailable()->getTotalItemsCount();
    $this->assertEquals(3, $total);

    // Check that the last created item is first in order.
    $indexingOrder = $index->getTrackerInstanceIfAvailable()->getRemainingItems();
    $this->assertTrue(str_contains($indexingOrder[0], '3'));
    $this->assertTrue(str_contains($indexingOrder[1], '2'));
    $this->assertTrue(str_contains($indexingOrder[2], '1'));
  }

}
