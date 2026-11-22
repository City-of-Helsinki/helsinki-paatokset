<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Calendar\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\paatokset_ahjo_api\Entity\Meeting;
use Drupal\paatokset_ahjo_api\Entity\MeetingPhase;
use Drupal\paatokset_ahjo_api\Entity\Policymaker;
use Drupal\paatokset_ahjo_api\Calendar\DTO\MeetingCalendarItem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for retrieving meeting data.
 */
final class CalendarController extends ControllerBase {

  /**
   * Number of meetings loaded into memory at once.
   */
  private const int CHUNK_SIZE = 100;

  public function __construct(
    private readonly RendererInterface $renderer,
    private readonly TimeInterface $time,
  ) {
  }

  /**
   * Retrieves meetings grouped by date.
   *
   * Supported query parameters:
   *  - from: (string) date string, for example Y-m-d.
   *  - to: (string) date string, for example Y-m-d.
   *  - agenda_published: (bool) only meetings with published agenda.
   *  - minutes_published: (bool) only meetings with published minutes.
   *  - policymaker: (string) policymaker ID.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Response with meetings or errors.
   */
  public function query(Request $request) : Response {
    $query = $this->entityTypeManager()
      ->getStorage('node')
      ->getQuery()
      ->accessCheck()
      ->condition('type', 'meeting')
      ->condition('status', 1)
      ->sort('field_meeting_date');

    foreach (['from' => '>=', 'to' => '<='] as $param => $operator) {
      if (!$value = $request->query->get($param)) {
        continue;
      }

      if (!strtotime($value)) {
        return new JsonResponse([
          'errors' => "Parameter '$param' cannot be converted to timestamp.",
        ], Response::HTTP_BAD_REQUEST);
      }

      $date = (new DrupalDateTime($value))
        ->setTimezone(new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE))
        ->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);

      $query->condition('field_meeting_date', $date, $operator);
    }

    if ($request->query->get('agenda_published')) {
      $query->condition('field_meeting_agenda_published', TRUE);
    }
    if ($request->query->get('minutes_published')) {
      $query->condition('field_meeting_minutes_published', TRUE);
    }
    if ($policymaker = $request->query->get('policymaker')) {
      $query->condition('field_meeting_dm_id', $policymaker);
    }

    $ids = $query->execute();

    // Url generation bubbles cacheability metadata, which must be
    // collected for cacheable responses.
    $context = new RenderContext();
    $meetings = $this->renderer->executeInRenderContext($context, fn () => $this->loadMeetings($ids));

    $cache = new CacheableMetadata();
    $cache->addCacheContexts(['url']);
    $cache->addCacheTags(['node_list:meeting']);

    if (!$context->isEmpty()) {
      $cache = $cache->merge($context->pop());
    }

    $response = new CacheableJsonResponse(['data' => $meetings]);
    $response->addCacheableDependency($cache);
    return $response;
  }

  /**
   * Loads meetings and builds the response data.
   *
   * @param array<int|string, int|string> $ids
   *   Meeting node ids in display order.
   *
   * @return array<string, list<\Drupal\paatokset_ahjo_api\Calendar\DTO\MeetingCalendarItem>>
   *   Meeting data grouped by date (Y-m-d).
   */
  private function loadMeetings(array $ids): array {
    $storage = $this->entityTypeManager()->getStorage('node');
    $langcode = $this->languageManager()->getCurrentLanguage()->getId();

    $data = [];
    foreach (array_chunk($ids, self::CHUNK_SIZE) as $chunk) {
      $meetings = array_filter($storage->loadMultiple($chunk), static fn ($meeting) => $meeting instanceof Meeting);
      $policymakers = $this->loadPolicymakers($meetings);

      foreach ($meetings as $meeting) {
        $policymaker = $policymakers[$meeting->get('field_meeting_dm_id')->value] ?? NULL;
        if ($item = $this->buildItem($meeting, $policymaker, $langcode)) {
          $data[date('Y-m-d', $item->meetingDate)][] = $item;
        }
      }

      $storage->resetCache($chunk);
    }

    return $data;
  }

  /**
   * Loads policymakers of given meetings.
   *
   * @param \Drupal\paatokset_ahjo_api\Entity\Meeting[] $meetings
   *   Meetings.
   *
   * @return \Drupal\paatokset_ahjo_api\Entity\Policymaker[]
   *   Policymakers keyed by policymaker id.
   */
  private function loadPolicymakers(array $meetings): array {
    $ids = [];
    foreach ($meetings as $meeting) {
      if ($id = $meeting->get('field_meeting_dm_id')->value) {
        $ids[$id] = $id;
      }
    }

    if (!$ids) {
      return [];
    }

    $policymakers = [];
    $entities = $this->entityTypeManager()
      ->getStorage('node')
      ->loadByProperties([
        'type' => 'policymaker',
        'field_policymaker_id' => array_values($ids),
      ]);

    foreach ($entities as $policymaker) {
      if ($policymaker instanceof Policymaker) {
        $policymakers[$policymaker->getPolicymakerId()] = $policymaker;
      }
    }

    return $policymakers;
  }

  /**
   * Builds response data for single meeting.
   *
   * @param \Drupal\paatokset_ahjo_api\Entity\Meeting $meeting
   *   The meeting.
   * @param \Drupal\paatokset_ahjo_api\Entity\Policymaker|null $policymaker
   *   The policymaker of the meeting.
   * @param string $langcode
   *   Response language.
   *
   * @return \Drupal\paatokset_ahjo_api\Calendar\DTO\MeetingCalendarItem|null
   *   Meeting data or NULL if the meeting should not be shown.
   */
  private function buildItem(Meeting $meeting, ?Policymaker $policymaker, string $langcode): ?MeetingCalendarItem {
    $timestamp = $meeting->get('field_meeting_date')->date?->getTimestamp();
    if ($timestamp === NULL) {
      return NULL;
    }

    $status = $meeting->get('field_meeting_status')->value;
    $cancelled = $status === 'peruttu';

    // Only show future cancelled meetings in the calendar, not past ones.
    if ($cancelled && $timestamp < $this->time->getCurrentTime()) {
      return NULL;
    }

    $origTimestamp = $meeting->get('field_meeting_date_original')->date?->getTimestamp();
    $moved = !$cancelled && $origTimestamp && $origTimestamp !== $timestamp;

    $additionalInfo = match (TRUE) {
      $cancelled => $this->t('Meeting cancelled'),
      $moved => $this->t('Meeting moved'),
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

    return new MeetingCalendarItem(
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

}
