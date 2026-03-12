<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\paatokset_ahjo_api\Entity\CaseBundle;
use Drupal\paatokset_ahjo_api\Hook\DecisionReindexerHook;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\paatokset_ahjo_api\Kernel\AhjoEntityKernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests DecisionReindexerHook.
 */
#[Group('paatokset_ahjo_api')]
#[RunTestsInSeparateProcesses]
class DecisionReindexerHookTest extends AhjoEntityKernelTestBase {

  use NodeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'search_api',
    'search_api_db',
  ];

  /**
   * The search index.
   */
  private Index $index;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

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

    $this->index = Index::create([
      'id' => 'decisions',
      'name' => 'Decisions',
      'status' => TRUE,
      'datasource_settings' => [
        'entity:node' => [],
      ],
      'server' => 'server',
      'tracker_settings' => [
        'default' => [],
      ],
    ]);
    $this->index->setServer($server);
    $this->index->save();
  }

  /**
   * Tests that updating a case marks related decisions for reindexing.
   */
  public function testProcessor(): void {
    $case = $this->createNode([
      'type' => 'case',
      'status' => 1,
      'field_diary_number' => 'HEL 2024-000001',
    ]);
    $this->assertInstanceOf(CaseBundle::class, $case);

    $decision1 = $this->createNode([
      'type' => 'decision',
      'field_diary_number' => 'HEL 2024-000001',
    ]);
    $decision2 = $this->createNode([
      'type' => 'decision',
      'field_diary_number' => 'HEL 2024-000001',
    ]);

    // Unrelated decision.
    $this->createNode([
      'type' => 'decision',
      'field_diary_number' => 'HEL 2024-999999',
    ]);

    // Mark all items as indexed so we can detect changes.
    $this->index->indexItems();

    $tracker = $this->index->getTrackerInstance();
    $this->assertEquals(0, $tracker->getRemainingItemsCount());

    // Trigger the hook.
    $hook = new DecisionReindexerHook(
      $this->container->get(EntityTypeManagerInterface::class),
    );
    $hook->update($case);

    $remaining = $tracker->getRemainingItems();
    $this->assertCount(2, $remaining);
    $this->assertContains($this->formatItemId($decision1), $remaining);
    $this->assertContains($this->formatItemId($decision2), $remaining);
  }

  /**
   * Formats a node into a search_api item ID.
   */
  private function formatItemId(NodeInterface $node): string {
    return 'entity:node/' . $node->id() . ':' . $node->language()->getId();
  }

}
