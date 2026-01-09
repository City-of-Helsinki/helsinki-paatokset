<?php

// phpcs:ignoreFile

namespace Drupal\paatokset_ahjo_api\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Utility\Error;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Service class for retrieving meeting-related data.
 *
 * @package Drupal\paatokset_ahjo_api\Services
 */
class MeetingService {

  use StringTranslationTrait;

  /**
   * Machine name for meeting node type.
   */
  const NODE_TYPE = 'meeting';

  /**
   * Constructs MeetingService.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(
    private EntityTypeManagerInterface $entityTypeManager,
    private readonly LanguageManagerInterface $languageManager,
    #[Autowire(service: 'logger.channel.paatokset_ahjo_api')]
    private readonly LoggerInterface $logger,
  ) {
  }

  /**
   * Query Ahjo API meetings from ElasticSearch Index.
   *
   * @param array $params
   *   Containing query parameters
   *   $params = [
   *     from  => (string) time string in format Y-m-d.
   *     to    => (string) time string in format Y-m-d.
   *     sort  => (string) ASC or DESC.
   *     agenda_published => (bool).
   *     minutes_published => (bool).
   *     policymaker => (string) policymaker ID.
   *     limit => (int) Limit results. Defaults to 10000 (ES maximum).
   *     policymaker_name => (string) policymaker name.
   *   ].
   *
   * @return array
   *   of meetings.
   */
  public function elasticQuery(array $params = []) : array {
    if (isset($params['sort'])) {
      $sort = $params['sort'];
    }
    else {
      $sort = 'ASC';
    }

    /** @var \Drupal\search_api\IndexInterface $index */
    $index = $this->entityTypeManager->getStorage('search_api_index')->load('meetings');

    $query = $index
      ->query()
      ->sort('field_meeting_date', $sort);

    if (isset($params['from'])) {
      $this->validateTime($params['from'], 'from');
      $query->addCondition('field_meeting_date', $params['from'], '>=');
    }
    if (isset($params['to'])) {
      $this->validateTime($params['to'], 'to');
      $query->addCondition('field_meeting_date', $params['to'], '<=');
    }

    // Set default limit to ES maximum if not already set in params.
    if (isset($params['limit'])) {
      $query->range(0, $params['limit']);
    }
    else {
      $query->range(0, 10000);
    }

    if (isset($params['agenda_published'])) {
      $query->addCondition('field_agenda_published', TRUE);
    }
    if (isset($params['minutes_published'])) {
      $query->addCondition('field_minutes_published', TRUE);
    }

    if (isset($params['not_cancelled'])) {
      $query->addCondition('field_meeting_status', 'peruttu', '<>');
    }

    if (isset($params['policymaker'])) {
      $query->addCondition('field_meeting_dm_id', $params['policymaker']);
    }
    if (isset($params['policymaker_name'])) {
      $query->addCondition('field_meeting_dm', $params['policymaker_name']);
    }

    try {
      $results = $query->execute();
    }
    catch (\Throwable $exception) {
      Error::logException($this->logger, $exception);
      return [];
    }

    $langcode = $this->languageManager->getCurrentLanguage()->getId();

    $data = [];
    foreach ($results as $result) {
      $timestamp = $result->getField('field_meeting_date')->getValues()[0];
      $date = date('Y-m-d', $timestamp);

      // Check if meeting was moved.
      $orig_timestamp = $result->getField('field_meeting_date_original')->getValues()[0];

      // Check if meeting is cancelled and determine if it should be visible.
      $meeting_cancelled = FALSE;
      if ($result->getField('field_meeting_status')->getValues()[0] === 'peruttu') {
        $meeting_cancelled = TRUE;
      }
      // Only show future cancelled meetings in the calendar, not past ones.
      if ($meeting_cancelled && isset($params['only_future_cancelled'])) {
        $now = strtotime('now');
        if ($meeting_cancelled && $timestamp < $now) {
          continue;
        }
      }

      // Determine which kind of additional info to show.
      $additional_info = NULL;
      $meeting_moved = FALSE;
      if ($meeting_cancelled) {
        $additional_info = $this->t('Meeting cancelled');
      }
      elseif ($orig_timestamp && $orig_timestamp !== $timestamp) {
        $additional_info = $this->t('Meeting moved');
        $meeting_moved = TRUE;
      }

      $phase = $result->getField('meeting_phase')->getValues()[0];
      $policymaker_id = $result->getField('field_meeting_dm_id')->getValues()[0];

      $item = [
        'title' => $result->getField('title')->getValues()[0],
        'meeting_date' => $timestamp,
        'meeting_moved' => $meeting_moved,
        'meeting_cancelled' => $meeting_cancelled,
        'policymaker' => $policymaker_id,
        'start_time' => date('H:i', $timestamp),
        'orig_time' => date('d.m. H:i', $orig_timestamp),
        'phase' => $phase,
        'status' => $result->getField('field_meeting_status')->getValues()[0],
        'additional_info' => $additional_info,
      ];

      // Get JSON data for policymaker data.
      $dm_json = $result->getField('meeting_dm_data')->getValues();
      $dm_data = [];
      if (!empty($dm_json)) {
        $dm_data = json_decode($dm_json[0], TRUE);
      }

      // Set DM values.
      if (isset($dm_data['type'])) {
        $item['policymaker_type'] = $dm_data['type'];
      }

      if (isset($dm_data['title'][$langcode])) {
        $item['policymaker_name'] = $dm_data['title'][$langcode];
      }

      // Get JSON data for meeting URLs, but only if it's not cancelled.
      $url_json = NULL;
      if (!$meeting_cancelled) {
        $url_json = $result->getField('meeting_url')->getValues();
      }
      $url_data = [];
      if (!empty($url_json)) {
        $url_data = json_decode($url_json[0], TRUE);
      }

      // Set correct links.
      if ($phase === 'minutes' && isset($url_data['meeting_link'][$langcode])) {
        $item['minutes_link'] = $url_data['meeting_link'][$langcode];
      }
      elseif ($phase === 'decision' && isset($url_data['decision_link'][$langcode])) {
        $item['decision_link'] = $url_data['decision_link'][$langcode];
      }
      elseif ($phase === 'agenda' && isset($url_data['meeting_link'][$langcode])) {
        $item['motions_list_link'] = $url_data['meeting_link'][$langcode];
      }

      // Group based on day.
      $data[$date][] = $item;
    }

    return $data;
  }

  /**
   * Get the next scheduled meeting.
   *
   * @param string $id
   *   Policymaker id.
   *
   * @return string|void
   *   Meeting date as string if found
   */
  public function nextMeetingDate(string $id) {
    $queryResult = $this->elasticQuery([
      'from' => date('Y-m-d', strtotime('now')),
      'limit' => 1,
      'policymaker' => $id,
    ]);

    if (!empty($queryResult)) {
      $meeting = reset($queryResult);
      $meeting = reset($meeting);
      return $meeting['meeting_date'];
    }
  }

  /**
   * Check if time is valid.
   *
   * @param string $time
   *   String in time format Y-m-d.
   * @param string $tag
   *   Param key for identification.
   *
   * @throws \Exception
   */
  private function validateTime($time, $tag) {
    if (!strtotime($time)) {
      throw new \Exception("Parameter '$tag' cannot be converted to timestamp.");
    }
  }

}
