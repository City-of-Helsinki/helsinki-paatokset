<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\Entity;

use Drupal\Core\Cache\Cache;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\paatokset_ahjo_api\Kernel\AhjoEntityKernelTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests custom cache tag invalidation of Ahjo entities.
 */
#[Group('paatokset_ahjo_api')]
#[RunTestsInSeparateProcesses]
class CacheInvalidationTest extends AhjoEntityKernelTestBase {

  use NodeCreationTrait;

  /**
   * Tests that insert, update and delete invalidate the expected tags.
   *
   * @param array<string, mixed> $values
   *   Values of the created node.
   * @param array<string, mixed> $changes
   *   Values changed on update.
   * @param string[] $insert
   *   Tags invalidated on insert.
   * @param string[] $update
   *   Tags invalidated on update.
   * @param string[] $delete
   *   Tags invalidated on delete.
   */
  #[DataProvider('invalidationData')]
  public function testInvalidation(array $values, array $changes, array $insert, array $update, array $delete): void {
    $allTags = array_unique([...$insert, ...$update, ...$delete]);

    $entity = $this->assertInvalidates(
      $allTags,
      $insert,
      fn () => $this->createNode($values),
    );

    $this->assertInvalidates($allTags, $update, function () use ($entity, $changes) {
      foreach ($changes as $field => $value) {
        $entity->set($field, $value);
      }
      $entity->save();
    });

    $this->assertInvalidates($allTags, $delete, fn () => $entity->delete());
  }

  /**
   * Data provider for testInvalidation().
   *
   * @return iterable<string, array{array<string, mixed>, array<string, mixed>, string[], string[], string[]}>
   *   Test cases.
   */
  public static function invalidationData(): iterable {
    // Moving a decision to another case invalidates both cases.
    yield 'decision' => [
      [
        'type' => 'decision',
        'field_meeting_id' => 'meeting-123',
        'field_policymaker_id' => 'pm-456',
        'field_diary_number' => 'HEL-789',
      ],
      ['field_diary_number' => 'HEL-000'],
      ['ahjo_case:HEL-789', 'meeting:meeting-123', 'decision_pm:pm-456'],
      ['ahjo_case:HEL-789', 'ahjo_case:HEL-000', 'meeting:meeting-123', 'decision_pm:pm-456'],
      ['ahjo_case:HEL-000', 'meeting:meeting-123', 'decision_pm:pm-456'],
    ];

    yield 'case' => [
      [
        'type' => 'case',
        'field_diary_number' => 'HEL-789',
      ],
      ['field_diary_number' => 'HEL-000'],
      ['ahjo_case:HEL-789'],
      ['ahjo_case:HEL-789', 'ahjo_case:HEL-000'],
      ['ahjo_case:HEL-000'],
    ];

    // Moving a meeting to another policymaker invalidates both policymakers.
    yield 'meeting' => [
      [
        'type' => 'meeting',
        'field_meeting_dm_id' => 'dm-123',
      ],
      ['field_meeting_dm_id' => 'dm-456'],
      ['meeting_pm:dm-123'],
      ['meeting_pm:dm-123', 'meeting_pm:dm-456'],
      ['meeting_pm:dm-456'],
    ];
  }

  /**
   * Asserts that the callback invalidates exactly the expected tags.
   *
   * @param string[] $allTags
   *   Tags to check.
   * @param string[] $expected
   *   Tags that should be invalidated.
   * @param callable $callback
   *   The operation.
   *
   * @return mixed
   *   Return value of the callback.
   */
  private function assertInvalidates(array $allTags, array $expected, callable $callback): mixed {
    $cache = $this->container->get('cache.default');

    foreach ($allTags as $tag) {
      $cache->set($tag, TRUE, Cache::PERMANENT, [$tag]);
    }

    $result = $callback();

    foreach ($allTags as $tag) {
      if (in_array($tag, $expected, TRUE)) {
        $this->assertFalse($cache->get($tag));
      }
      else {
        $this->assertNotFalse($cache->get($tag));
      }
    }

    return $result;
  }

}
