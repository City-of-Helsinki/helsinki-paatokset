<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\SearchApi\Processor;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Utility\Utility;
use Drupal\Tests\paatokset_ahjo_api\Kernel\SearchApi\AhjoSearchApiKernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests more_decisions search_api processor.
 */
#[Group('paatokset_ahjo_api')]
#[RunTestsInSeparateProcesses]
class MoreDecisionsTest extends AhjoSearchApiKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public function setUp($processor = NULL): void {
    parent::setUp('more_decisions');

    $field = new Field($this->index, 'test_more_decisions');
    $field->setType('boolean');
    $field->setPropertyPath('more_decisions');
    $field->setDatasourceId('entity:node');
    $field->setLabel('More decisions');

    $this->index->addField($field);
    $this->index->save();
  }

  /**
   * Tests search api processor.
   */
  public function testProcessor(): void {
    $this->createCase('HEL 2024-000001');
    $decision = $this->createDecision('HEL 2024-000001');

    // Tests that more_decisions is FALSE when only one decision exists.
    $this->assertMoreDecisionsField(FALSE, $decision);

    $this->createCase('HEL 2024-000002');
    $decision1 = $this->createDecision('HEL 2024-000002');
    $decision2 = $this->createDecision('HEL 2024-000002');

    // Tests that more_decisions is TRUE when multiple decisions exist.
    $this->assertMoreDecisionsField(TRUE, $decision1);
    $this->assertMoreDecisionsField(TRUE, $decision2);

    $decision = $this->createDecision('HEL 2024-000003');

    // Tests that more_decisions is FALSE when decision has no case.
    $this->assertMoreDecisionsField(FALSE, $decision);
  }

  /**
   * Creates a case node with the given diary number.
   */
  private function createCase(string $diaryNumber): NodeInterface {
    $storage = $this->container->get(EntityTypeManagerInterface::class)
      ->getStorage('node');

    $case = $storage->create([
      'type' => 'case',
      'title' => 'Test case ' . $diaryNumber,
      'status' => 1,
      'field_diary_number' => $diaryNumber,
    ]);
    $case->save();
    return $case;
  }

  /**
   * Creates a decision node with the given diary number.
   */
  private function createDecision(string $diaryNumber): NodeInterface {
    $storage = $this->container->get(EntityTypeManagerInterface::class)
      ->getStorage('node');

    $decision = $storage->create([
      'type' => 'decision',
      'title' => 'Test decision',
      'field_diary_number' => $diaryNumber,
    ]);
    $decision->save();
    return $decision;
  }

  /**
   * Asserts the more_decisions field value for a decision.
   */
  private function assertMoreDecisionsField(bool $expected, NodeInterface $decision): void {
    $id = Utility::createCombinedId('entity:node', $decision->id() . ':' . $decision->language()->getId());
    $item = $this->container
      ->get('search_api.fields_helper')
      ->createItemFromObject($this->index, $decision->getTypedData(), $id);

    $fields = $item->getFields();

    $this->assertEquals([$expected], $fields['test_more_decisions']->getValues());
  }

}
