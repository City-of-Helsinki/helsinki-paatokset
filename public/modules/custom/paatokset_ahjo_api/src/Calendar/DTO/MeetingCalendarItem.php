<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Calendar\DTO;

/**
 * Meeting calendar item DTO.
 */
final readonly class MeetingCalendarItem implements \JsonSerializable {

  public function __construct(
    public string $title,
    public int $meetingDate,
    public bool $meetingMoved,
    public bool $meetingCancelled,
    public ?string $policymaker,
    public string $startTime,
    public string $origTime,
    public ?string $phase,
    public ?string $status,
    public ?string $additionalInfo = NULL,
    public ?string $policymakerType = NULL,
    public ?string $policymakerName = NULL,
    public ?string $minutesLink = NULL,
    public ?string $decisionLink = NULL,
    public ?string $motionsListLink = NULL,
  ) {}

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   *   Meeting data for the calendar.
   */
  public function jsonSerialize(): array {
    $optional = array_filter([
      'policymaker_type' => $this->policymakerType,
      'policymaker_name' => $this->policymakerName,
      'minutes_link' => $this->minutesLink,
      'decision_link' => $this->decisionLink,
      'motions_list_link' => $this->motionsListLink,
    ], static fn (?string $value) => $value !== NULL);

    return [
      'title' => $this->title,
      'meeting_date' => $this->meetingDate,
      'meeting_moved' => $this->meetingMoved,
      'meeting_cancelled' => $this->meetingCancelled,
      'policymaker' => $this->policymaker,
      'start_time' => $this->startTime,
      'orig_time' => $this->origTime,
      'phase' => $this->phase,
      'status' => $this->status,
      'additional_info' => $this->additionalInfo,
    ] + $optional;
  }

}
