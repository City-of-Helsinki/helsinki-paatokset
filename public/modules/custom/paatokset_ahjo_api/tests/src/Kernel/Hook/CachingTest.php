<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\Hook;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\paatokset_ahjo_api\Entity\Decision;
use Drupal\paatokset_ahjo_api\Entity\Meeting;
use Drupal\paatokset_ahjo_api\Hook\Caching;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\paatokset_ahjo_api\Kernel\AhjoEntityKernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests cache invalidation hooks.
 */
#[Group('paatokset_ahjo_api')]
class CachingTest extends AhjoEntityKernelTestBase {

  use NodeCreationTrait;

  /**
   * Tests decision insert invalidates cache tags.
   */
  public function testDecisionInsertInvalidatesCacheTags(): void {
    $invalidator = $this->createMock(CacheTagsInvalidatorInterface::class);
    $invalidator->expects($this->once())
      ->method('invalidateTags')
      ->with(['ahjo_case:HEL-789', 'meeting:meeting-123', 'decision_pm:pm-456']);

    $caching = new Caching($invalidator);

    $decision = $this->createNode([
      'type' => 'decision',
      'field_meeting_id' => 'meeting-123',
      'field_policymaker_id' => 'pm-456',
      'field_diary_number' => 'HEL-789',
    ]);

    $this->assertInstanceOf(Decision::class, $decision);
    $caching->insert($decision);
  }

  /**
   * Tests meeting insert invalidates cache tags.
   */
  public function testMeetingInsertInvalidatesCacheTags(): void {
    $invalidator = $this->createMock(CacheTagsInvalidatorInterface::class);
    $invalidator->expects($this->once())
      ->method('invalidateTags')
      ->with(['meeting_pm:dm-123']);

    $caching = new Caching($invalidator);

    $meeting = $this->createNode([
      'type' => 'meeting',
      'field_meeting_dm_id' => 'dm-123',
    ]);

    $this->assertInstanceOf(Meeting::class, $meeting);
    $caching->insert($meeting);
  }

}
