<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_allu\Unit;

use Drupal\elasticsearch_connector\Event\DeleteParamsEvent;
use Drupal\elasticsearch_connector\Event\IndexParamsEvent;
use Drupal\paatokset_allu\EventSubscriber\ApprovalSplitSubscriber;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the Allu approval split subscriber.
 */
#[Group('paatokset_allu')]
#[CoversClass(ApprovalSplitSubscriber::class)]
class ApprovalSplitSubscriberTest extends UnitTestCase {

  private const INDEX = 'paatokset_allu';

  /**
   * Tests that other indexes are not altered.
   */
  public function testOtherIndexIsSkipped(): void {
    $params = $this->indexParams([
      'entity:paatokset_allu_document/1:und' => ['approval_type' => ['WORK_FINISHED']],
    ]);
    $event = new IndexParamsEvent(self::INDEX, $params, 'decisions');

    (new ApprovalSplitSubscriber())->onIndexParams($event);

    $this->assertEquals($params, $event->getParams());
  }

  /**
   * Tests splitting documents in bulk index requests.
   */
  public function testIndexParams(): void {
    $event = new IndexParamsEvent(self::INDEX, $this->indexParams([
      'entity:paatokset_allu_approval/5:und' => ['type' => ['WORK_FINISHED']],
      'entity:paatokset_allu_document/1:und' => [],
      'entity:paatokset_allu_document/2:und' => ['approval_type' => ['WORK_FINISHED']],
      'entity:paatokset_allu_document/3:und' => [
        'approval_type' => ['OPERATIONAL_CONDITION', 'WORK_FINISHED', 'WORK_FINISHED'],
      ],
    ]), 'allu');

    (new ApprovalSplitSubscriber())->onIndexParams($event);

    $this->assertEquals([
      // Approval items are not altered.
      $this->indexAction('entity:paatokset_allu_approval/5:und'),
      $this->source('entity:paatokset_allu_approval/5:und', ['type' => ['WORK_FINISHED']]),
      // Document without approvals: stale split documents are removed.
      $this->indexAction('entity:paatokset_allu_document/1:und'),
      $this->source('entity:paatokset_allu_document/1:und'),
      $this->deleteAction('entity:paatokset_allu_document/1:und__split_1'),
      $this->deleteAction('entity:paatokset_allu_document/1:und__split_2'),
      // Document with one approval type.
      $this->indexAction('entity:paatokset_allu_document/2:und'),
      $this->source('entity:paatokset_allu_document/2:und'),
      $this->indexAction('entity:paatokset_allu_document/2:und__split_1'),
      $this->source('entity:paatokset_allu_document/2:und__split_1', ['approval_type' => ['WORK_FINISHED']]),
      $this->deleteAction('entity:paatokset_allu_document/2:und__split_2'),
      // Document with two approval types (duplicates are ignored).
      $this->indexAction('entity:paatokset_allu_document/3:und'),
      $this->source('entity:paatokset_allu_document/3:und'),
      $this->indexAction('entity:paatokset_allu_document/3:und__split_1'),
      $this->source('entity:paatokset_allu_document/3:und__split_1', ['approval_type' => ['OPERATIONAL_CONDITION']]),
      $this->indexAction('entity:paatokset_allu_document/3:und__split_2'),
      $this->source('entity:paatokset_allu_document/3:und__split_2', ['approval_type' => ['WORK_FINISHED']]),
    ], $event->getParams()['body']);
  }

  /**
   * Tests that split documents are deleted with the document.
   */
  public function testDeleteParams(): void {
    $event = new DeleteParamsEvent(self::INDEX, [
      'index' => self::INDEX,
      'body' => [
        $this->deleteAction('entity:paatokset_allu_approval/5:und'),
        $this->deleteAction('entity:paatokset_allu_document/1:und'),
      ],
    ]);

    (new ApprovalSplitSubscriber())->onDeleteParams($event);

    $this->assertEquals([
      'index' => self::INDEX,
      'body' => [
        $this->deleteAction('entity:paatokset_allu_approval/5:und'),
        $this->deleteAction('entity:paatokset_allu_document/1:und'),
        $this->deleteAction('entity:paatokset_allu_document/1:und__split_1'),
        $this->deleteAction('entity:paatokset_allu_document/1:und__split_2'),
      ],
    ], $event->getParams());
  }

  /**
   * Builds bulk index params like elasticsearch_connector does.
   *
   * @param array<string, array<string, array<mixed>>> $documents
   *   Extra document fields keyed by item ID.
   *
   * @return array<string, mixed>
   *   The params.
   */
  private function indexParams(array $documents): array {
    $params = [];
    foreach ($documents as $id => $fields) {
      $params['body'][] = $this->indexAction($id);
      $params['body'][] = $this->source($id, $fields);
    }
    return $params;
  }

  /**
   * Builds a bulk index action.
   *
   * @return array<string, mixed>
   *   The action.
   */
  private function indexAction(string $id): array {
    return ['index' => ['_id' => $id, '_index' => self::INDEX]];
  }

  /**
   * Builds a bulk delete action.
   *
   * @return array<string, mixed>
   *   The action.
   */
  private function deleteAction(string $id): array {
    return ['delete' => ['_index' => self::INDEX, '_id' => $id]];
  }

  /**
   * Builds a document source.
   *
   * @param string $id
   *   The document ID.
   * @param array<string, array<mixed>> $fields
   *   Extra fields.
   *
   * @return array<string, mixed>
   *   The document source.
   */
  private function source(string $id, array $fields = []): array {
    return $fields + [
      'search_api_id' => [$id],
      'label' => ['AL123'],
    ];
  }

}
