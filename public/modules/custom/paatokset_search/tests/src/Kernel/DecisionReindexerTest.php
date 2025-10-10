<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_search\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\paatokset_ahjo_api\Entity\CaseBundle;
use Drupal\paatokset_search\DecisionReindexer;
use Drupal\search_api\IndexInterface;

/**
 * Tests for DecisionReindexer service.
 */
class DecisionReindexerTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'paatokset_search',
    'node',
    'user',
    'search_api',
  ];

  /**
   * Tests onEntityChange calls trackItemsUpdated correctly.
   *
   * @covers ::onEntityChange
   * @covers ::getRelatedDecisions
   * @covers ::getDecisionsIndex
   */
  public function testOnEntityChangeCallsTrackItemsUpdated(): void {
    $entity_type_manager = $this->container->get('entity_type.manager');

    $mock_index = $this->createMock(IndexInterface::class);

    // @phpstan-ignore-next-line
    $mock_index->expects($this->exactly(2))
      ->method('trackItemsUpdated')
      ->withConsecutive(
        ['entity:node', ['123:fi']],
        ['entity:node', ['124:sv']]
      );

    $reindexer = $this->getMockBuilder(DecisionReindexer::class)
      ->setConstructorArgs([$entity_type_manager])
      ->onlyMethods(['getRelatedDecisions', 'getDecisionsIndex'])
      ->getMock();

    $reindexer->expects($this->once())
      ->method('getRelatedDecisions')
      ->willReturn([
        ['id' => '123', 'langcode' => 'fi'],
        ['id' => '124', 'langcode' => 'sv'],
      ]);

    $reindexer->expects($this->once())
      ->method('getDecisionsIndex')
      ->willReturn($mock_index);

    $case = $this->createMock(CaseBundle::class);
    $case->method('getEntityTypeId')->willReturn('node');
    $case->method('bundle')->willReturn('case');

    $reindexer->onEntityChange($case);
  }

  /**
   * Tests onEntityChange does nothing when no decisions found.
   *
   * @covers ::onEntityChange
   * @covers ::getRelatedDecisions
   * @covers ::getDecisionsIndex
   */
  public function testOnEntityChangeWithNoDecisions(): void {
    $entity_type_manager = $this->container->get('entity_type.manager');

    $mock_index = $this->createMock(IndexInterface::class);

    $mock_index->expects($this->never())
      ->method('trackItemsUpdated');

    $reindexer = $this->getMockBuilder(DecisionReindexer::class)
      ->setConstructorArgs([$entity_type_manager])
      ->onlyMethods(['getRelatedDecisions', 'getDecisionsIndex'])
      ->getMock();

    $reindexer->expects($this->once())
      ->method('getRelatedDecisions')
      ->willReturn([]);

    // getDecisionsIndex should not be called if no decisions.
    $reindexer->expects($this->never())
      ->method('getDecisionsIndex');

    $case = $this->createMock(CaseBundle::class);
    $case->method('getEntityTypeId')->willReturn('node');
    $case->method('bundle')->willReturn('case');

    $reindexer->onEntityChange($case);
  }

}
