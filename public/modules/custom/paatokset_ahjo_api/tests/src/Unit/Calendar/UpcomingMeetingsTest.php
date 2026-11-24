<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Unit\Calendar;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\paatokset_ahjo_api\Calendar\UpcomingMeetings;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestWith;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests upcoming meetings service.
 */
#[Group('paatokset_ahjo_api')]
class UpcomingMeetingsTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * Tests cache max age.
   */
  #[TestWith(['2026-01-15T21:00:00Z', 3600], '23:00 in Helsinki (UTC+2)')]
  #[TestWith(['2026-01-15T22:00:00Z', 86400], '00:00 in Helsinki: a full day')]
  #[TestWith(['2026-06-15T20:00:00Z', 3600], '23:00 in Helsinki during summer time (UTC+3)')]
  public function testCacheMaxAge(string $now, int $expected): void {
    $this->assertSame($expected, $this->getSut((new \DateTimeImmutable($now))->getTimestamp())->getCacheMaxAge());
  }

  /**
   * Gets the service with mocked time.
   */
  private function getSut(int $now): UpcomingMeetings {
    $time = $this->prophesize(TimeInterface::class);
    $time->getCurrentTime()->willReturn($now);

    return new UpcomingMeetings(
      $this->prophesize(EntityTypeManagerInterface::class)->reveal(),
      $this->prophesize(EntityRepositoryInterface::class)->reveal(),
      $time->reveal(),
    );
  }

}
