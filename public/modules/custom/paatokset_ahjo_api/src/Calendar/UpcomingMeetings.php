<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Calendar;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\paatokset_ahjo_api\Entity\Meeting;

/**
 * Loads upcoming meetings.
 */
final readonly class UpcomingMeetings {

  public function __construct(
    private EntityTypeManagerInterface $entityTypeManager,
    private EntityRepositoryInterface $entityRepository,
    private TimeInterface $time,
  ) {
  }

  /**
   * Loads upcoming meetings.
   *
   * @param string|null $policymakerId
   *   Only load meetings of this policymaker.
   * @param bool $excludeCancelled
   *   Exclude cancelled meetings.
   * @param int|null $limit
   *   Maximum number of meetings.
   *
   * @return \Drupal\paatokset_ahjo_api\Entity\Meeting[]
   *   Meetings in the current language, ordered by meeting date.
   */
  public function load(?string $policymakerId = NULL, bool $excludeCancelled = FALSE, ?int $limit = NULL): array {
    $today = $this->getToday()
      ->setTimezone(new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE))
      ->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);

    $storage = $this->entityTypeManager->getStorage('node');
    $query = $storage->getQuery()
      ->accessCheck()
      ->condition('type', 'meeting')
      ->condition('status', 1)
      ->condition('field_meeting_date', $today, '>=')
      ->sort('field_meeting_date');

    if ($policymakerId !== NULL) {
      $query->condition('field_meeting_dm_id', $policymakerId);
    }

    if ($excludeCancelled) {
      $query->condition('field_meeting_status', 'peruttu', '<>');
    }

    if ($limit !== NULL) {
      $query->range(0, $limit);
    }

    $result = [];
    foreach ($storage->loadMultiple($query->execute()) as $meeting) {
      assert($meeting instanceof Meeting);
      $result[] = $this->entityRepository->getTranslationFromContext($meeting);
    }

    return $result;
  }

  /**
   * Gets the number of seconds until the results of load() change.
   */
  public function getCacheMaxAge(): int {
    $tomorrow = $this->getToday()->modify('+1 day');

    return max(1, $tomorrow->getTimestamp() - $this->time->getCurrentTime());
  }

  /**
   * Gets the start of the current day.
   */
  private function getToday(): \DateTimeImmutable {
    return \DateTimeImmutable::createFromTimestamp($this->time->getCurrentTime())
      ->setTimezone(new \DateTimeZone('Europe/Helsinki'))
      ->setTime(0, 0);
  }

}
