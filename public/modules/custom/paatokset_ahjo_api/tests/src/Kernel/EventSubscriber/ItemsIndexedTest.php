<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\EventSubscriber;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\paatokset_ahjo_api\EventSubscriber\ItemsIndexed;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\Event\ItemsIndexedEvent;
use Drupal\search_api\Utility\Utility;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\paatokset_ahjo_api\Kernel\AhjoEntityKernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests ItemsIndexed event subscriber.
 */
#[Group('paatokset_ahjo_api')]
#[RunTestsInSeparateProcesses]
class ItemsIndexedTest extends AhjoEntityKernelTestBase {

  use NodeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'search_api',
    'search_api_db',
  ];

  /**
   * Tests event subscriber.
   */
  public function testEventSubscriber(): void {
    $index = $this->createSearchIndex('decisions');

    // Tests that trustee decisions invalidate decision_pm cache tags.
    $invalidator = $this->createMock(CacheTagsInvalidatorInterface::class);
    $invalidator
      ->expects($this->once())
      ->method('invalidateTags')
      ->with(['decision_pm:pm-123']);

    $event = new ItemsIndexedEvent($index, [
      $this->formatItemId($this->createNode([
        'type' => 'decision',
        'field_organization_type' => 'Viranhaltija',
        'field_policymaker_id' => 'pm-123',
      ])),
    ]);

    $subscriber = new ItemsIndexed($invalidator);
    $subscriber->itemsIndexed($event);

    // Tests that non-trustee decisions do not invalidate cache tags.
    $invalidator = $this->createMock(CacheTagsInvalidatorInterface::class);
    $invalidator
      ->expects($this->never())
      ->method('invalidateTags');

    $event = new ItemsIndexedEvent($index, [
      $this->formatItemId($this->createNode([
        'type' => 'decision',
        'field_organization_type' => 'SomeOtherType',
        'field_policymaker_id' => 'pm-456',
      ])),
    ]);

    $subscriber = new ItemsIndexed($invalidator);
    $subscriber->itemsIndexed($event);
  }

  /**
   * Tests that meetings index invalidates meeting_pm cache tags.
   */
  public function testMeetingsIndexInvalidatesCacheTags(): void {
    $index = $this->createSearchIndex('meetings');

    $invalidator = $this->createMock(CacheTagsInvalidatorInterface::class);
    $invalidator->expects($this->once())
      ->method('invalidateTags')
      ->with(['meeting_pm:dm-456']);

    $event = new ItemsIndexedEvent($index, [
      $this->formatItemId($this->createNode([
        'type' => 'meeting',
        'field_meeting_dm_id' => 'dm-456',
      ])),
    ]);

    $subscriber = new ItemsIndexed($invalidator);
    $subscriber->itemsIndexed($event);
  }

  /**
   * Creates a search_api index with a node datasource.
   */
  protected function createSearchIndex(string $id): Index {
    $this->installSchema('search_api', ['search_api_item']);
    $this->installEntitySchema('search_api_task');
    $this->installConfig('search_api');

    $server = Server::create([
      'id' => 'server',
      'name' => 'Server',
      'status' => TRUE,
      'backend' => 'search_api_db',
      'backend_config' => [
        'min_chars' => 3,
        'database' => 'default:default',
      ],
    ]);
    $server->save();

    $index = Index::create([
      'id' => $id,
      'name' => $id,
      'status' => TRUE,
      'datasource_settings' => [
        'entity:node' => [],
      ],
      'server' => 'server',
      'tracker_settings' => [
        'default' => [],
      ],
    ]);
    $index->setServer($server);
    $index->save();

    return $index;
  }

  /**
   * Formats a node into a search_api combined item ID.
   */
  private function formatItemId($node): string {
    return Utility::createCombinedId(
      'entity:node',
      $node->id() . ':' . $node->language()->getId(),
    );
  }

}
