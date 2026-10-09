<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_allu\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\paatokset_allu\ApprovalType;
use Drupal\paatokset_allu\Entity\Approval;
use Drupal\paatokset_allu\Entity\Document;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\IndexInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that approval changes reindex the parent document.
 */
#[Group('paatokset_allu')]
#[RunTestsInSeparateProcesses]
class ApprovalReindexHooksTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'search_api',
    'search_api_test',
    'helfi_api_base',
    'paatokset_allu',
    'diff',
  ];

  /**
   * The search index.
   */
  private IndexInterface $index;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('search_api', ['search_api_item']);
    $this->installEntitySchema('search_api_task');
    $this->installEntitySchema('paatokset_allu_document');
    $this->installEntitySchema('paatokset_allu_approval');

    Server::create([
      'id' => 'test',
      'name' => 'Test',
      'backend' => 'search_api_test',
    ])->save();

    $this->index = Index::create([
      'id' => 'allu',
      'name' => 'Allu',
      'server' => 'test',
      'datasource_settings' => [
        'entity:paatokset_allu_document' => [],
      ],
      'tracker_settings' => [
        'default' => [],
      ],
      'options' => [
        'index_directly' => FALSE,
      ],
    ]);
    $this->index->save();
  }

  /**
   * Tests approval insert, update and delete.
   */
  public function testApprovalChangesReindexDocument(): void {
    $document = Document::create(['label' => 'first', 'type' => 'EVENT']);
    $document->save();
    $other = Document::create(['label' => 'second', 'type' => 'EVENT']);
    $other->save();

    $approval = Approval::create([
      'type' => ApprovalType::WORK_FINISHED->value,
      'document' => $document,
    ]);

    // Insert.
    $this->markAllIndexed();
    $approval->save();
    $this->assertRemaining([$document]);

    // Moving the approval reindexes both the new and the old document.
    $this->markAllIndexed();
    $approval = Approval::load($approval->id());
    $approval->set('document', $other);
    $approval->save();
    $this->assertRemaining([$document, $other]);

    // Delete.
    $this->markAllIndexed();
    $approval->delete();
    $this->assertRemaining([$other]);

    // Approvals without a document are ignored.
    $this->markAllIndexed();
    Approval::create(['type' => ApprovalType::WORK_FINISHED->value])->save();
    $this->assertRemaining([]);
  }

  /**
   * Marks all tracked items as indexed.
   */
  private function markAllIndexed(): void {
    $tracker = $this->index->getTrackerInstance();
    $tracker->trackItemsIndexed($tracker->getRemainingItems());
    $this->assertEmpty($tracker->getRemainingItems());
  }

  /**
   * Asserts that the given documents are waiting for indexing.
   *
   * @param \Drupal\paatokset_allu\Entity\Document[] $documents
   *   The expected documents.
   */
  private function assertRemaining(array $documents): void {
    $expected = array_map(
      static fn (Document $document) => "entity:paatokset_allu_document/{$document->id()}:{$document->language()->getId()}",
      $documents,
    );
    $actual = $this->index->getTrackerInstance()->getRemainingItems();

    sort($expected);
    sort($actual);
    $this->assertEquals($expected, $actual);
  }

}
