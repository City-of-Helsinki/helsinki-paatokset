<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_helsinki_kanava\Kernel;

use Drupal\block\Entity\Block;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\paatokset_ahjo_api\Entity\Policymaker;
use Drupal\paatokset_ahjo_api\Entity\PolicymakerId;
use Drupal\paatokset_helsinki_kanava\Plugin\Block\AnnouncementsBlock;
use Drupal\Tests\paatokset_ahjo_api\Kernel\AhjoEntityKernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the live stream announcement.
 */
#[Group('paatokset_helsinki_kanava')]
#[RunTestsInSeparateProcesses]
class AnnouncementBlockTest extends AhjoEntityKernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'paatokset_helsinki_kanava',
    'block',
  ];

  /**
   * {@inheritDoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('meeting_video');
    $this->setUpCurrentUser(permissions: ['access content']);
  }

  /**
   * Tests that block max age works correctly.
   */
  public function testBlockMaxAge(): void {
    // 12:00 in Helsinki.
    $now = (new \DateTimeImmutable('2025-01-15 10:00:00', new \DateTimeZone('UTC')))->getTimestamp();

    $time = $this->prophesize(TimeInterface::class);
    $time->getCurrentTime()->willReturn($now);
    $time->getRequestTime()->willReturn($now);
    $this->container->set('datetime.time', $time->reveal());

    Policymaker::create([
      'type' => 'policymaker',
      'status' => 1,
      'title' => 'City council',
      'field_policymaker_id' => PolicymakerId::CityCouncil->value,
    ])->save();

    $plugin = Block::create([
      'plugin' => 'paatokset_helsinki_kanava_announcements',
      'region' => 'footer',
      'id' => $this->randomMachineName(),
    ])->getPlugin();

    // No upcoming meetings.
    $this->assertCacheMaxAge(AnnouncementsBlock::MIN_CACHE_TTL, $plugin->build());

    // Meeting started 30 seconds ago.
    $this->setMeeting($now - 30);
    $this->assertCacheMaxAge(AnnouncementsBlock::MIN_CACHE_TTL, $plugin->build());

    // Live-stream is coming, cache until the live stream starts.
    $this->setMeeting($now + AnnouncementsBlock::MIN_CACHE_TTL + 30);
    $this->assertCacheMaxAge(AnnouncementsBlock::MIN_CACHE_TTL + 30, $plugin->build());

    // Alert is not shown yet.
    $this->setMeeting($now + 864000);
    $this->assertCacheMaxAge(864000 - AnnouncementsBlock::ALERT_OFFSET, $plugin->build());
  }

  /**
   * Replaces existing meetings with a single council meeting.
   */
  private function setMeeting(int $timestamp): void {
    $storage = $this->container->get(EntityTypeManagerInterface::class)->getStorage('node');
    $storage->delete($storage->loadByProperties(['type' => 'meeting']));

    $storage->create([
      'type' => 'meeting',
      'status' => 1,
      'title' => 'Council meeting',
      'field_meeting_id' => 'meeting-' . $timestamp,
      'field_meeting_dm_id' => PolicymakerId::CityCouncil->value,
      'field_meeting_date' => \DateTimeImmutable::createFromTimestamp($timestamp)
        ->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
    ])->save();
  }

  /**
   * Asserts render array max cache age.
   *
   * @param int $expectedMaxAge
   *   Expected max age.
   * @param array<string, mixed> $build
   *   The render array.
   */
  private function assertCacheMaxAge(int $expectedMaxAge, array $build): void {
    $this->assertEquals($expectedMaxAge, $build['#cache']['max-age']);
  }

}
