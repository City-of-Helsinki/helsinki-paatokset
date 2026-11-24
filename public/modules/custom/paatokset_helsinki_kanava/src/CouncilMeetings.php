<?php

declare(strict_types=1);

namespace Drupal\paatokset_helsinki_kanava;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\paatokset_ahjo_api\Entity\Meeting;
use Drupal\paatokset_ahjo_api\Entity\PolicymakerId;

/**
 * Looks up city council meetings for Helsinki Kanava live streams.
 */
class CouncilMeetings {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TimeInterface $time,
  ) {
  }

  /**
   * Gets the start time of the next city council meeting.
   *
   * @return int|null
   *   Unix timestamp of the next meeting, or NULL if none is scheduled.
   */
  public function nextMeetingTimestamp(): ?int {
    $storage = $this->entityTypeManager->getStorage('node');

    $today = \DateTimeImmutable::createFromTimestamp($this->time->getCurrentTime())
      ->setTimezone(new \DateTimeZone('Europe/Helsinki'))
      ->setTime(0, 0)
      ->setTimezone(new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE))
      ->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);

    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'meeting')
      ->condition('field_meeting_dm_id', PolicymakerId::CityCouncil->value)
      ->condition('field_meeting_date', $today, '>=')
      ->sort('field_meeting_date')
      ->range(0, 1)
      ->execute();

    $meeting = $ids ? $storage->load(reset($ids)) : NULL;

    return $meeting instanceof Meeting ? $meeting->getMeetingTimestamp() : NULL;
  }

}
