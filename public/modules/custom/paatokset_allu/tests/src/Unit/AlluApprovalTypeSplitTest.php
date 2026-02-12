<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_allu\Unit;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
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
use PHPUnit\Framework\MockObject\MockObject;
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
  protected IndexInterface&MockObject $index;

  /**
   * The mock approval entity storage.
   */
  protected EntityStorageInterface&MockObject $approvalStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->setUpMockContainer();

    $this->approvalStorage = $this->createMock(EntityStorageInterface::class);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')
      ->with('paatokset_allu_approval')
      ->willReturn($this->approvalStorage);
    $this->container->set('entity_type.manager', $entityTypeManager);

    $this->processor = new AlluApprovalTypeSplit([], 'allu_approval_type_split', [], $this->container->get('entity_type.manager'));
    $this->index = $this->createMock(IndexInterface::class);

    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')
      ->willReturn($this->createMock(LoggerInterface::class));
    $this->container->set('logger.factory', $loggerFactory);
  }

  /**
   * Tests that non-document items and single-type items are not split.
   */
  public function testAlterItemsSkipsItemsThatDontNeedSplitting(): void {
    $items = $this->createTestItems(['entity:paatokset_allu_approval/1:und']);

    $original_keys = array_keys($items);
    $this->processor->alterIndexedItems($items);

    $this->assertEquals($original_keys, array_keys($items));
  }

  /**
   * Tests that items with a single approval type are not split.
   */
  public function testAlterItemsSkipsSingleApprovalType(): void {
    $items = $this->createTestItems(['entity:paatokset_allu_document/1:und']);

    $this->approvalStorage->method('loadByProperties')
      ->willReturn([$this->createApprovalEntity('OPERATIONAL_CONDITION')]);

    $this->processor->alterIndexedItems($items);

    $this->assertCount(1, $items);
    $this->assertNull($items['entity:paatokset_allu_document/1:und']->getExtraData('split_approval_type'));
  }

  /**
   * Tests splitting, item properties, and the no-recursion guarantee.
   */
  public function testAlterItemsSplitsMultipleTypes(): void {
    $items = $this->createTestItems(['entity:paatokset_allu_document/1:und']);
    $original_object = $items['entity:paatokset_allu_document/1:und']->getOriginalObject(FALSE);

    $call_count = 0;
    $this->approvalStorage->method('loadByProperties')
      ->willReturnCallback(function () use (&$call_count): array {
        $call_count++;
        return [
          $this->createApprovalEntity('OPERATIONAL_CONDITION'),
          $this->createApprovalEntity('WORK_FINISHED'),
        ];
      });

    $this->processor->alterIndexedItems($items);

    // Original kept + 1 split added.
    $this->assertCount(2, $items);

    // Original item gets the first type.
    $original = $items['entity:paatokset_allu_document/1:und'];
    $this->assertEquals('OPERATIONAL_CONDITION', $original->getExtraData('split_approval_type'));

    // Split item gets the second type and shares the original object.
    $split = $items['entity:paatokset_allu_document/1:und__split_1'];
    $this->assertEquals('WORK_FINISHED', $split->getExtraData('split_approval_type'));
    $this->assertSame($original_object, $split->getOriginalObject(FALSE));
    $this->assertEquals('und', $split->getLanguage());
    $this->assertEquals('entity:paatokset_allu_document', $split->getDatasourceId());

    // Storage was only queried for the original item, not the split.
    $this->assertEquals(1, $call_count);
  }

  /**
   * Tests a mixed batch with document and non-document items.
   */
  public function testAlterItemsMixedBatch(): void {
    $items = $this->createTestItems([
      'entity:paatokset_allu_approval/1:und',
      'entity:paatokset_allu_document/1:und',
      'entity:paatokset_allu_document/2:und',
    ]);

    $this->approvalStorage->method('loadByProperties')
      ->willReturnCallback(function (array $properties): array {
        if ($properties['document'] === 1) {
          return [
            $this->createApprovalEntity('OPERATIONAL_CONDITION'),
            $this->createApprovalEntity('WORK_FINISHED'),
          ];
        }
        return [$this->createApprovalEntity('OPERATIONAL_CONDITION')];
      });

    $this->processor->alterIndexedItems($items);

    // Approval (1) + document/1 split into 2 + document/2 unchanged (1) = 4.
    $this->assertCount(4, $items);
    $this->assertArrayHasKey('entity:paatokset_allu_document/1:und__split_1', $items);
    $this->assertArrayNotHasKey('entity:paatokset_allu_document/2:und__split_1', $items);
  }

  /**
   * Tests that preprocessIndexItems overrides approval_type for split items.
   */
  public function testPreprocessSetsTargetType(): void {
    $item = new Item($this->index, 'entity:paatokset_allu_document/1:und');
    $item->setExtraData('split_approval_type', 'WORK_FINISHED');

    $field = new Field($this->index, 'approval_type');
    $field->setType('string');
    $field->setValues(['OPERATIONAL_CONDITION', 'WORK_FINISHED']);
    $item->setField('approval_type', $field);

    $this->processor->preprocessIndexItems([$item]);

    $this->assertEquals(['WORK_FINISHED'], $field->getValues());
  }

  /**
   * Tests that preprocessIndexItems does not modify non-split items.
   */
  public function testPreprocessSkipsNonSplitItems(): void {
    $item = new Item($this->index, 'entity:paatokset_allu_document/1:und');

    $field = new Field($this->index, 'approval_type');
    $field->setType('string');
    $field->setValues(['OPERATIONAL_CONDITION', 'WORK_FINISHED']);
    $item->setField('approval_type', $field);

    $this->processor->preprocessIndexItems([$item]);

    $this->assertEquals(['OPERATIONAL_CONDITION', 'WORK_FINISHED'], $field->getValues());
  }

  /**
   * Creates test items with mock entities.
   *
   * @param list<string> $item_ids
   *   Full item IDs (e.g. "entity:paatokset_allu_document/1:und").
   *
   * @return array<string, \Drupal\search_api\Item\ItemInterface>
   *   The created items keyed by item ID.
   */
  protected function createTestItems(array $item_ids): array {
    $items = [];
    foreach ($item_ids as $item_id) {
      [$datasource_id, $raw_id] = Utility::splitCombinedId($item_id);
      $entity_id = (int) explode(':', $raw_id)[0];

      $item = new Item($this->index, $item_id);
      $item->setLanguage('und');

      $entity = $this->createMock(ContentEntityInterface::class);
      $entity->method('id')->willReturn($entity_id);

      $original_object = $this->createMock(ComplexDataInterface::class);
      $original_object->method('getValue')->willReturn($entity);
      $item->setOriginalObject($original_object);

      $datasource = $this->createMock(DatasourceInterface::class);
      $datasource->method('getItemLanguage')->willReturn('und');
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
    // Use stdClass because the processor accesses ->value via __get() on the
    // real FieldItemList. Interface mocks don't provide __get().
    $field_item_list = (object) ['value' => $type];

    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('get')
      ->with('type')
      ->willReturn($field_item_list);

    return $entity;
  }

}
