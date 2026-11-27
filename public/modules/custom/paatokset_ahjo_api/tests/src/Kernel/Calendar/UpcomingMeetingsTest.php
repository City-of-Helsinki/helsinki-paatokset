<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\Calendar;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\paatokset_ahjo_api\Calendar\UpcomingMeetings;
use Drupal\Tests\paatokset_ahjo_api\Kernel\AhjoEntityKernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests upcoming meetings service.
 */
#[Group('paatokset_ahjo_api')]
#[RunTestsInSeparateProcesses]
class UpcomingMeetingsTest extends AhjoEntityKernelTestBase {

  use ProphecyTrait;

  /**
   * Tests loading meetings.
   */
  public function testLoad(): void {
    // 2026-01-15 12:00 in Helsinki (UTC+2).
    $service = $this->getSut(strtotime('2026-01-15T10:00:00Z'));

    $storage = $this->container->get(EntityTypeManagerInterface::class)
      ->getStorage('node');

    // Meeting dates are stored in UTC.
    $meetings = [
      'yesterday' => ['2026-01-14T12:00:00', 'pidetty', 'dm-1', 1],
      // 2026-01-15 01:00 in Helsinki: before "now" but today.
      'early_today' => ['2026-01-14T23:00:00', 'pidetty', 'dm-1', 1],
      'tomorrow' => ['2026-01-16T10:00:00', 'vahvistettu', 'dm-1', 1],
      'cancelled' => ['2026-01-17T10:00:00', 'peruttu', 'dm-1', 1],
      'other_policymaker' => ['2026-01-18T10:00:00', 'vahvistettu', 'dm-2', 1],
      'unpublished' => ['2026-01-19T10:00:00', 'vahvistettu', 'dm-1', 0],
      'later' => ['2026-02-01T10:00:00', 'vahvistettu', 'dm-1', 1],
    ];

    foreach ($meetings as $title => [$date, $status, $policymaker, $published]) {
      $storage->create([
        'type' => 'meeting',
        'status' => $published,
        'title' => $title,
        'field_meeting_id' => "meeting-$title",
        'field_meeting_dm_id' => $policymaker,
        'field_meeting_date' => $date,
        'field_meeting_status' => $status,
      ])->save();
    }

    $titles = static fn (array $meetings) => array_map(static fn ($meeting) => $meeting->label(), $meetings);

    $this->assertSame(
      ['early_today', 'tomorrow', 'cancelled', 'other_policymaker', 'later'],
      $titles($service->load()),
    );
    $this->assertSame(
      ['early_today', 'tomorrow', 'cancelled', 'later'],
      $titles($service->load('dm-1')),
    );
    $this->assertSame(
      ['early_today', 'tomorrow', 'other_policymaker'],
      $titles($service->load(excludeCancelled: TRUE, limit: 3)),
    );
    $this->assertSame([], $service->load('dm-3'));
  }

  /**
   * Gets the service with mocked time.
   */
  private function getSut(int $now): UpcomingMeetings {
    $time = $this->prophesize(TimeInterface::class);
    $time->getCurrentTime()->willReturn($now);

    return new UpcomingMeetings(
      $this->container->get(EntityTypeManagerInterface::class),
      $this->container->get(EntityRepositoryInterface::class),
      $time->reveal(),
    );
  }

}
