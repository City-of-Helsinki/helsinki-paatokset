<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_allu\Unit;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\paatokset_allu\Plugin\search_api\processor\AlluApprovalTypeSplit;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Item\Item;
use Drupal\search_api\Utility\Utility;
use Drupal\Tests\search_api\Unit\Processor\TestItemsTrait;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LoggerInterface;

/**
 * Tests the Allu approval type split processor.
 */
#[Group('paatokset_allu')]
#[CoversClass(AlluApprovalTypeSplit::class)]
class AlluApprovalTypeSplitTest extends UnitTestCase {

  use TestItemsTrait;

  /**
   * The processor under test.
   */
  protected AlluApprovalTypeSplit $processor;

  /**
   * The mock index.
   */
  protected IndexInterface $index;

  /**
   * The mock entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The mock approval entity storage.
   */
  protected EntityStorageInterface $approvalStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->setUpMockContainer();

    $this->processor = new AlluApprovalTypeSplit([], 'allu_approval_type_split', []);
    $this->index = $this->createMock(IndexInterface::class);

    // Set up the entity type manager mock.
    $this->approvalStorage = $this->createMock(EntityStorageInterface::class);
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->entityTypeManager->method('getStorage')
      ->with('paatokset_allu_approval')
      ->willReturn($this->approvalStorage);
    $this->container->set('entity_type.manager', $this->entityTypeManager);

    // Logger for the processor's notice messages.
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')
      ->willReturn($this->createMock(LoggerInterface::class));
    $this->container->set('logger.factory', $logger_factory);
  }

  /**
   * Tests that non-document items are not affected by alterIndexedItems.
   */
  public function testAlterItemsSkipsNonDocumentItems(): void {
    $items = $this->createTestItems([
      'entity:paatokset_allu_approval/1:und' => [],
    ]);

    $original_keys = array_keys($items);
    $this->processor->alterIndexedItems($items);

    $this->assertEquals($original_keys, array_keys($items));
  }

  /**
   * Tests that items with no approvals are not split.
   */
  public function testAlterItemsSkipsNoApprovals(): void {
    $items = $this->createTestItems([
      'entity:paatokset_allu_document/1:und' => [],
    ]);

    $this->approvalStorage->method('loadByProperties')
      ->willReturn([]);

    $original_keys = array_keys($items);
    $this->processor->alterIndexedItems($items);

    $this->assertEquals($original_keys, array_keys($items));
    $this->assertNull($items['entity:paatokset_allu_document/1:und']->getExtraData('split_approval_type'));
  }

  /**
   * Tests that items with a single approval type are not split.
   */
  public function testAlterItemsSkipsSingleApprovalType(): void {
    $items = $this->createTestItems([
      'entity:paatokset_allu_document/1:und' => [],
    ]);

    $this->approvalStorage->method('loadByProperties')
      ->willReturn([
        $this->createApprovalEntity('OPERATIONAL_CONDITION'),
      ]);

    $original_keys = array_keys($items);
    $this->processor->alterIndexedItems($items);

    $this->assertEquals($original_keys, array_keys($items));
    $this->assertNull($items['entity:paatokset_allu_document/1:und']->getExtraData('split_approval_type'));
  }

  /**
   * Tests that items with multiple approval types get split.
   */
  public function testAlterItemsSplitsMultipleTypes(): void {
    $items = $this->createTestItems([
      'entity:paatokset_allu_document/1:und' => [],
    ]);

    $this->approvalStorage->method('loadByProperties')
      ->willReturn([
        $this->createApprovalEntity('OPERATIONAL_CONDITION'),
        $this->createApprovalEntity('WORK_FINISHED'),
      ]);

    $this->processor->alterIndexedItems($items);

    $this->assertCount(2, $items);
    $this->assertArrayHasKey('entity:paatokset_allu_document/1:und', $items);
    $this->assertArrayHasKey('entity:paatokset_allu_document/1:und__split_1', $items);
  }

  /**
   * Tests that the original item is kept and assigned the first type.
   */
  public function testAlterItemsKeepsOriginalWithFirstType(): void {
    $items = $this->createTestItems([
      'entity:paatokset_allu_document/1:und' => [],
    ]);

    $this->approvalStorage->method('loadByProperties')
      ->willReturn([
        $this->createApprovalEntity('OPERATIONAL_CONDITION'),
        $this->createApprovalEntity('WORK_FINISHED'),
      ]);

    $this->processor->alterIndexedItems($items);

    $original = $items['entity:paatokset_allu_document/1:und'];
    $this->assertEquals('OPERATIONAL_CONDITION', $original->getExtraData('split_approval_type'));
  }

  /**
   * Tests that split items have correct properties.
   */
  public function testSplitItemProperties(): void {
    $items = $this->createTestItems([
      'entity:paatokset_allu_document/1:und' => [],
    ]);
    $original_object = $items['entity:paatokset_allu_document/1:und']->getOriginalObject(FALSE);

    $this->approvalStorage->method('loadByProperties')
      ->willReturn([
        $this->createApprovalEntity('OPERATIONAL_CONDITION'),
        $this->createApprovalEntity('WORK_FINISHED'),
      ]);

    $this->processor->alterIndexedItems($items);

    $split = $items['entity:paatokset_allu_document/1:und__split_1'];

    // Split item shares the original object.
    $this->assertSame($original_object, $split->getOriginalObject(FALSE));

    // Split item has the correct language.
    $this->assertEquals('und', $split->getLanguage());

    // Split item has the second approval type.
    $this->assertEquals('WORK_FINISHED', $split->getExtraData('split_approval_type'));

    // Split item preserves the datasource ID.
    $this->assertEquals('entity:paatokset_allu_document', $split->getDatasourceId());
  }

  /**
   * Tests splitting with more than two approval types.
   */
  public function testAlterItemsThreeTypes(): void {
    $items = $this->createTestItems([
      'entity:paatokset_allu_document/1:und' => [],
    ]);

    $this->approvalStorage->method('loadByProperties')
      ->willReturn([
        $this->createApprovalEntity('TYPE_A'),
        $this->createApprovalEntity('TYPE_B'),
        $this->createApprovalEntity('TYPE_C'),
      ]);

    $this->processor->alterIndexedItems($items);

    $this->assertCount(3, $items);
    $this->assertEquals('TYPE_A', $items['entity:paatokset_allu_document/1:und']->getExtraData('split_approval_type'));
    $this->assertEquals('TYPE_B', $items['entity:paatokset_allu_document/1:und__split_1']->getExtraData('split_approval_type'));
    $this->assertEquals('TYPE_C', $items['entity:paatokset_allu_document/1:und__split_2']->getExtraData('split_approval_type'));
  }

  /**
   * Tests that duplicate approval types are deduplicated.
   */
  public function testAlterItemsDeduplicatesTypes(): void {
    $items = $this->createTestItems([
      'entity:paatokset_allu_document/1:und' => [],
    ]);

    $this->approvalStorage->method('loadByProperties')
      ->willReturn([
        $this->createApprovalEntity('OPERATIONAL_CONDITION'),
        $this->createApprovalEntity('OPERATIONAL_CONDITION'),
        $this->createApprovalEntity('WORK_FINISHED'),
      ]);

    $this->processor->alterIndexedItems($items);

    // 3 approvals but only 2 unique types → 2 items.
    $this->assertCount(2, $items);
  }

  /**
   * Tests a mixed batch with both document and non-document items.
   */
  public function testAlterItemsMixedBatch(): void {
    $items = $this->createTestItems([
      'entity:paatokset_allu_approval/1:und' => [],
      'entity:paatokset_allu_document/1:und' => [],
      'entity:paatokset_allu_document/2:und' => [],
      'entity:paatokset_allu_approval/2:und' => [],
    ]);

    $this->approvalStorage->method('loadByProperties')
      ->willReturnCallback(function (array $properties) {
        if ($properties['document'] === 1) {
          return [
            $this->createApprovalEntity('OPERATIONAL_CONDITION'),
            $this->createApprovalEntity('WORK_FINISHED'),
          ];
        }
        // Document 2 has only one approval type.
        return [$this->createApprovalEntity('OPERATIONAL_CONDITION')];
      });

    $this->processor->alterIndexedItems($items);

    // Approval items unchanged (2) + document/1 split (2) + document/2 not
    // split (1) = 5.
    $this->assertCount(5, $items);
    $this->assertArrayHasKey('entity:paatokset_allu_approval/1:und', $items);
    $this->assertArrayHasKey('entity:paatokset_allu_approval/2:und', $items);
    $this->assertArrayHasKey('entity:paatokset_allu_document/1:und', $items);
    $this->assertArrayHasKey('entity:paatokset_allu_document/1:und__split_1', $items);
    $this->assertArrayHasKey('entity:paatokset_allu_document/2:und', $items);
  }

  /**
   * Tests that split items are not re-split (no infinite recursion).
   */
  public function testAlterItemsDoesNotRevisitSplitItems(): void {
    $items = $this->createTestItems([
      'entity:paatokset_allu_document/1:und' => [],
    ]);

    $call_count = 0;
    $this->approvalStorage->method('loadByProperties')
      ->willReturnCallback(function () use (&$call_count) {
        $call_count++;
        return [
          $this->createApprovalEntity('OPERATIONAL_CONDITION'),
          $this->createApprovalEntity('WORK_FINISHED'),
        ];
      });

    $this->processor->alterIndexedItems($items);

    // loadByProperties should only be called once (for the original item),
    // not again for the split item.
    $this->assertEquals(1, $call_count);
  }

  /**
   * Tests that preprocessIndexItems overrides approval_type for split items.
   */
  public function testPreprocessSetsTargetType(): void {
    $item_id = 'entity:paatokset_allu_document/1:und';
    $item = new Item($this->index, $item_id);
    $item->setExtraData('split_approval_type', 'WORK_FINISHED');

    $field = new Field($this->index, 'approval_type');
    $field->setType('string');
    $field->setValues(['OPERATIONAL_CONDITION', 'WORK_FINISHED']);
    $item->setField('approval_type', $field);

    $items = [$item_id => $item];
    $this->processor->preprocessIndexItems($items);

    $this->assertEquals(['WORK_FINISHED'], $field->getValues());
  }

  /**
   * Tests that preprocessIndexItems does not modify non-split items.
   */
  public function testPreprocessSkipsNonSplitItems(): void {
    $item_id = 'entity:paatokset_allu_document/1:und';
    $item = new Item($this->index, $item_id);

    $field = new Field($this->index, 'approval_type');
    $field->setType('string');
    $field->setValues(['OPERATIONAL_CONDITION', 'WORK_FINISHED']);
    $item->setField('approval_type', $field);

    $items = [$item_id => $item];
    $this->processor->preprocessIndexItems($items);

    $this->assertEquals(['OPERATIONAL_CONDITION', 'WORK_FINISHED'], $field->getValues());
  }

  /**
   * Tests that approvals with empty type values are ignored.
   */
  public function testAlterItemsSkipsEmptyTypes(): void {
    $items = $this->createTestItems([
      'entity:paatokset_allu_document/1:und' => [],
    ]);

    $this->approvalStorage->method('loadByProperties')
      ->willReturn([
        $this->createApprovalEntity('OPERATIONAL_CONDITION'),
        $this->createApprovalEntity(''),
        $this->createApprovalEntity(NULL),
      ]);

    $this->processor->alterIndexedItems($items);

    // Only 1 valid unique type → no split.
    $this->assertCount(1, $items);
    $this->assertNull($items['entity:paatokset_allu_document/1:und']->getExtraData('split_approval_type'));
  }

  /**
   * Creates test items with mock entities for the given item IDs.
   *
   * @param array<string, array> $item_definitions
   *   Keys are full item IDs (e.g. "entity:paatokset_allu_document/1:und"),
   *   values are unused (reserved for future field definitions).
   *
   * @return \Drupal\search_api\Item\ItemInterface[]
   *   The created items keyed by item ID.
   */
  protected function createTestItems(array $item_definitions): array {
    $items = [];
    foreach ($item_definitions as $item_id => $definition) {
      [$datasource_id, $raw_id] = Utility::splitCombinedId($item_id);
      $entity_id = (int) explode(':', $raw_id)[0];

      $item = new Item($this->index, $item_id);
      $item->setLanguage('und');

      // Create a mock entity.
      $entity = $this->createMock(ContentEntityInterface::class);
      $entity->method('id')->willReturn($entity_id);

      // Wrap in ComplexDataInterface.
      $original_object = $this->createMock(ComplexDataInterface::class);
      $original_object->method('getValue')->willReturn($entity);
      $item->setOriginalObject($original_object);

      // Mock datasource for getItemLanguage.
      $datasource = $this->createMock(DatasourceInterface::class);
      $datasource->method('getItemLanguage')
        ->willReturn('und');
      $this->index->method('getDatasource')
        ->with($datasource_id)
        ->willReturn($datasource);

      $items[$item_id] = $item;
    }
    return $items;
  }

  /**
   * Creates a mock approval entity with the given type.
   *
   * @param string|null $type
   *   The approval type value.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The mock approval entity.
   */
  protected function createApprovalEntity(?string $type): ContentEntityInterface {
    $field_item_list = (object) ['value' => $type];

    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('get')
      ->with('type')
      ->willReturn($field_item_list);

    return $entity;
  }

}
