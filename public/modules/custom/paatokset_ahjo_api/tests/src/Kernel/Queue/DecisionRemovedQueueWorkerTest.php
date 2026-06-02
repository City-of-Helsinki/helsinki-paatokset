<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\Queue;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\paatokset_ahjo_api\Plugin\QueueWorker\DecisionRemovedQueueWorker;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\paatokset_ahjo_api\Kernel\AhjoEntityKernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the decision removed queue worker.
 */
#[Group('paatokset_ahjo_api')]
#[RunTestsInSeparateProcesses]
class DecisionRemovedQueueWorkerTest extends AhjoEntityKernelTestBase {

  use NodeCreationTrait;

  /**
   * Tests decision and cascade case removal across all scenarios.
   */
  public function testDecisionRemoval(): void {
    // Removing one of several decisions keeps the case.
    $diary1 = 'HEL 2026-000001';
    $case1 = $this->createCase($diary1);
    $decision1 = $this->createDecision('{025CDF09-9494-CA2F-B3B3-9D4DDA600001}', $diary1);
    $decision2 = $this->createDecision('{025CDF09-9494-CA2F-B3B3-9D4DDA600002}', $diary1);
    $this->processRemoval('{025CDF09-9494-CA2F-B3B3-9D4DDA600002}', $diary1);
    $this->assertNodeDeleted($decision2);
    $this->assertNodeExists($decision1);
    $this->assertNodeExists($case1);

    // Removing the last decision deletes the case.
    $this->processRemoval('{025CDF09-9494-CA2F-B3B3-9D4DDA600001}', $diary1);
    $this->assertNodeDeleted($decision1);
    $this->assertNodeDeleted($case1);

    // Removing a non-existent decision with a non-matching caseId
    // throws no error and leaves the unrelated case untouched.
    $unrelated = $this->createCase('HEL 2026-000005');
    $this->processRemoval('{025CDF09-9494-CA2F-B3B3-9D4DDA600040}', 'HEL 2026-000999');
    $this->assertNodeExists($unrelated);

    // A decision-less case referenced by the payload is cleaned up.
    $diary6 = 'HEL 2026-000006';
    $orphan = $this->createCase($diary6);
    $this->processRemoval('{025CDF09-9494-CA2F-B3B3-9D4DDA600050}', $diary6);
    $this->assertNodeDeleted($orphan);

    // A payload without an id is a no-op.
    $diary7 = 'HEL 2026-000007';
    $case7 = $this->createCase($diary7);
    $decision7 = $this->createDecision('{025CDF09-9494-CA2F-B3B3-9D4DDA600060}', $diary7);
    $this->getSut()->processItem([
      'id' => 'decisions',
      'content' => (object) [
        'caseId' => $diary7,
        'updatetype' => 'Removed',
      ],
    ]);
    $this->assertNodeExists($decision7);
    $this->assertNodeExists($case7);
  }

  /**
   * Processes a decision removed callback through the queue worker.
   */
  private function processRemoval(string $nativeId, string $caseId): void {
    $this->getSut()->processItem([
      'id' => 'decisions',
      'content' => (object) [
        'id' => $nativeId,
        'caseId' => $caseId,
        'updatetype' => 'Removed',
      ],
    ]);
  }

  /**
   * Creates a decision node.
   */
  private function createDecision(string $nativeId, string $diary, string $langcode = 'fi', int $status = NodeInterface::PUBLISHED): NodeInterface {
    return $this->createNode([
      'type' => 'decision',
      'status' => $status,
      'langcode' => $langcode,
      'field_decision_native_id' => $nativeId,
      'field_diary_number' => $diary,
    ]);
  }

  /**
   * Creates a case node.
   */
  private function createCase(string $diary): NodeInterface {
    return $this->createNode([
      'type' => 'case',
      'status' => NodeInterface::PUBLISHED,
      'field_diary_number' => $diary,
    ]);
  }

  /**
   * Asserts that a node still exists in storage.
   */
  private function assertNodeExists(NodeInterface $node): void {
    $this->assertNotNull($this->reloadNode($node), sprintf('Node %s should exist.', $node->id()));
  }

  /**
   * Asserts that a node has been deleted from storage.
   */
  private function assertNodeDeleted(NodeInterface $node): void {
    $this->assertNull($this->reloadNode($node), sprintf('Node %s should be deleted.', $node->id()));
  }

  /**
   * Reloads a node from storage.
   */
  private function reloadNode(NodeInterface $node): ?NodeInterface {
    $storage = $this->container->get(EntityTypeManagerInterface::class)->getStorage('node');
    $storage->resetCache([$node->id()]);
    return $storage->load($node->id());
  }

  /**
   * Gets the SUT.
   */
  private function getSut(): DecisionRemovedQueueWorker {
    return DecisionRemovedQueueWorker::create($this->container, [], DecisionRemovedQueueWorker::class, []);
  }

}
