<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Calendar\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\paatokset_ahjo_api\Entity\Policymaker;
use Drupal\paatokset_ahjo_api\Calendar\DTO\MeetingCalendarItem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for retrieving meeting data.
 */
final class CalendarController implements ContainerInjectionInterface {

  use AutowireTrait;

  /**
   * Number of months returned by a single request.
   */
  private const int MONTHS_PER_REQUEST = 3;

  /**
   * Hard limit for the number of meetings returned by a single request.
   */
  private const int MAX_RESULTS = 1000;

  public function __construct(
    private readonly RendererInterface $renderer,
    private readonly TimeInterface $time,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LanguageManagerInterface $languageManager,
  ) {
  }

  /**
   * Retrieves meetings grouped by date.
   *
   * Supported query parameters:
   *  - month: first month of the requested range in YYYY-MM.
   *    Meetings from self::MONTHS_PER_REQUEST months
   *    are returned.
   */
  public function query(Request $request) : Response {
    $month = $request->query->get('month');

    if (
      !is_string($month) ||
      !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) ||
      !($start = \DateTimeImmutable::createFromFormat('!Y-m', $month, new \DateTimeZone('Europe/Helsinki')))
    ) {
      return new JsonResponse([
        'errors' => "Parameter 'month' must be in format YYYY-MM.",
      ], Response::HTTP_BAD_REQUEST);
    }

    $end = $start->modify(sprintf('+%d months', self::MONTHS_PER_REQUEST));

    $query = $this->entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck();

    // Only show future cancelled meetings in the calendar, not past ones.
    $now = \DateTimeImmutable::createFromTimestamp($this->time->getCurrentTime());
    $notPastCancelled = $query->orConditionGroup()
      ->notExists('field_meeting_status')
      ->condition('field_meeting_status', 'peruttu', '<>')
      ->condition('field_meeting_date', self::toStorageDate($now), '>=');

    $ids = $query
      ->condition('type', 'meeting')
      ->condition('status', 1)
      // Padding ensures we can fully display the start and end weeks.
      ->condition('field_meeting_date', self::toStorageDate($start->modify('-1 week')), '>=')
      ->condition('field_meeting_date', self::toStorageDate($end->modify('+1 week')), '<')
      ->condition($notPastCancelled)
      ->sort('field_meeting_date')
      ->range(0, self::MAX_RESULTS)
      ->execute();

    // Url generation bubbles cacheability metadata.
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
   * Converts date to datetime field storage format.
   */
  private static function toStorageDate(\DateTimeImmutable $date): string {
    return $date
      ->setTimezone(new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE))
      ->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
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
    $storage = $this->entityTypeManager->getStorage('node');
    $langcode = $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_URL)->getId();

    /** @var \Drupal\paatokset_ahjo_api\Entity\Meeting[] $meetings */
    $meetings = $storage->loadMultiple($ids);
    $policymakers = $this->loadPolicymakers($meetings);

    $data = [];
    foreach ($meetings as $meeting) {
      $policymaker = $policymakers[$meeting->get('field_meeting_dm_id')->value] ?? NULL;
      if ($item = MeetingCalendarItem::fromMeeting($meeting, $policymaker, $langcode)) {
        $data[date('Y-m-d', $item->meetingDate)][] = $item;
      }
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
    $entities = $this->entityTypeManager
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

}
