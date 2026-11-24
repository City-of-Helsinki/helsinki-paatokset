<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Calendar\DTO;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\paatokset_ahjo_api\Entity\Meeting;
use Drupal\paatokset_ahjo_api\Entity\MeetingPhase;
use Drupal\paatokset_ahjo_api\Entity\Policymaker;

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
   * Creates calendar item from a meeting.
   *
   * @param \Drupal\paatokset_ahjo_api\Entity\Meeting $meeting
   *   The meeting.
   * @param \Drupal\paatokset_ahjo_api\Entity\Policymaker|null $policymaker
   *   The policymaker of the meeting.
   * @param string $langcode
   *   Response language.
   *
   * @return self|null
   *   Calendar item or NULL if the meeting has no date.
   */
  public static function fromMeeting(Meeting $meeting, ?Policymaker $policymaker, string $langcode): ?self {
    $timestamp = $meeting->get('field_meeting_date')->date?->getTimestamp();
    if ($timestamp === NULL) {
      return NULL;
    }

    $status = $meeting->get('field_meeting_status')->value;
    $cancelled = $status === 'peruttu';

    $origTimestamp = $meeting->get('field_meeting_date_original')->date?->getTimestamp();
    $moved = !$cancelled && $origTimestamp && $origTimestamp !== $timestamp;

    $additionalInfo = match (TRUE) {
      $cancelled => new TranslatableMarkup('Meeting cancelled'),
      $moved => new TranslatableMarkup('Meeting moved'),
      default => NULL,
    };

    $phase = $meeting->getMeetingPhase();

    $policymakerType = NULL;
    $policymakerName = NULL;
    if ($policymaker) {
      if (!$policymaker->get('field_organization_type')->isEmpty()) {
        $policymakerType = strtolower($policymaker->get('field_organization_type')->value);
      }

      if ($policymaker->hasTranslation($langcode)) {
        $translation = $policymaker->getTranslation($langcode);
        assert($translation instanceof Policymaker);
        $policymakerName = $translation->getPolicymakerName();
      }
    }

    $minutesLink = $decisionLink = $motionsListLink = NULL;
    if (!$cancelled && $policymaker && !$meeting->get('field_meeting_id')->isEmpty()) {
      match ($phase) {
        MeetingPhase::MINUTES => $minutesLink = $meeting->getMinutesUrl($langcode, $policymaker)?->toString(),
        MeetingPhase::DECISION => $decisionLink = $meeting->getDecisionAnnouncementUrl($langcode, $policymaker)?->toString(),
        MeetingPhase::AGENDA => $motionsListLink = $meeting->getMinutesUrl($langcode, $policymaker)?->toString(),
        default => NULL,
      };
    }

    return new self(
      title: (string) $meeting->label(),
      meetingDate: $timestamp,
      meetingMoved: $moved,
      meetingCancelled: $cancelled,
      policymaker: $meeting->get('field_meeting_dm_id')->value,
      startTime: date('H:i', $timestamp),
      origTime: date('d.m. H:i', $origTimestamp ?: $timestamp),
      phase: $phase?->value,
      status: $status,
      additionalInfo: $additionalInfo !== NULL ? (string) $additionalInfo : NULL,
      policymakerType: $policymakerType,
      policymakerName: $policymakerName,
      minutesLink: $minutesLink,
      decisionLink: $decisionLink,
      motionsListLink: $motionsListLink,
    );
  }

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
