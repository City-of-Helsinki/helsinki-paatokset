<?php

namespace Drupal\paatokset_ahjo_api\Service;

use Drupal\node\Entity\Node;

/**
 * Service class for retrieving meeting-related data.
 *
 * @package Drupal\paatokset_ahjo_api\Serivces
 */
class MeetingService {
  /**
   * Machine name for meeting node type.
   */
  const NODE_TYPE = 'meeting';

  /**
   * Query Ahjo API meetings from database.
   *
   * @param array $params
   *   Containing query parameters
   *   $params = [
   *     from  => (string) time string in format Y-m-d.
   *     to    => (string) time string in format Y-m-d.
   *     agenda_published => (bool).
   *     minutes_published => (bool).
   *     policymaker => (string) policymaker title.
   *   ].
   *
   * @return array
   *   of meetings.
   */
  public function query(array $params = []) : array {
    $query = \Drupal::entityQuery('node')
      ->condition('status', 1)
      ->condition('type', self::NODE_TYPE);

    if (isset($params['from'])) {
      $this->validateTime($params['from'], 'from');
      $query->condition('field_meeting_date', $params['from'], '>=');
    }
    if (isset($params['to'])) {
      $this->validateTime($params['to'], 'to');
      $query->condition('field_meeting_date', $params['to'], '<=');
    }
    if (isset($params['agenda_published'])) {
      $query->condition('field_agenda_published', TRUE);
    }
    if (isset($params['minutes_published'])) {
      $query->condition('field_minutes_published', TRUE);
    }

    // @todo Once policymakers are imported from Ahjo API, change this to use IDs
    if (isset($params['policymaker'])) {
      $query->condition('field_meeting_dm', $params['policymaker']);
    }

    $ids = $query->execute();

    if (empty($ids)) {
      return [];
    }

    $result = [];
    foreach (Node::loadMultiple($ids) as $node) {
      $date = date('Y-m-d', strtotime($node->get('field_meeting_date')->value));

      $transformedResult = [
        'title' => $node->get('title')->value,
        'policymaker' => $node->get('field_meeting_dm')->value,
        'start_time' => date('H:i', strtotime($node->get('field_meeting_date')->value)),
        // @todo Once motions get imported from Ahjo API, replace this with actual data
        'motions_list_link' => 'https://helsinki-paatokset.docker.so/',
      ];

      /*
      @todo Once documents get are imported from Ahjo API, return actual data
      For now, return dummy link to minutes if minutes_published is true
       */
      if ($node->get('field_meeting_minutes_published')->value) {
        $transformedResult['minutes_link'] = 'https://helsinki-paatokset.docker.so/';
      }

      $result[$date][] = $transformedResult;
    }

    return $result;
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
