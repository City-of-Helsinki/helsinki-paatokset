<?php

namespace Drupal\paatokset_ahjo_api\Service;

use Drupal\media\MediaInterface;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

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
   * Machine name for meeting document media type.
   */
  const DOCUMENT_TYPE = 'ahjo_document';

  /**
   * Query Ahjo API meetings from database.
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
   *     policymaker_name => (string) policymaker name.
   *   ].
   *
   * @return array
   *   of meetings.
   */
  public function query(array $params = []) : array {
    if (isset($params['sort'])) {
      $sort = $params['sort'];
    }
    else {
      $sort = 'ASC';
    }

    $query = \Drupal::entityQuery('node')
      ->condition('status', 1)
      ->condition('type', self::NODE_TYPE)
      ->sort('field_meeting_date', $sort);

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
    if (isset($params['limit'])) {
      $query->range('0', $params['limit']);
    }

    if (isset($params['policymaker'])) {
      $query->condition('field_meeting_dm_id', $params['policymaker']);
    }

    if (isset($params['policymaker_name'])) {
      $query->condition('field_meeting_dm', $params['policymaker_name']);
    }

    $ids = $query->execute();

    if (empty($ids)) {
      return [];
    }

    $result = [];
    foreach (Node::loadMultiple($ids) as $node) {
      $timestamp = $node->get('field_meeting_date')->date->getTimeStamp();
      $date = date('Y-m-d', $timestamp);

      $transformedResult = [
        'title' => $node->get('title')->value,
        'meeting_date' => $timestamp,
        'policymaker' => $node->get('field_meeting_dm_id')->value,
        'start_time' => date('H:i', $timestamp),
        // @todo Once motions get imported from Ahjo API, replace this with actual data
        'motions_list_link' => 'https://helsinki-paatokset.docker.so/',
      ];

      /*
      @todo Once documents get are imported from Ahjo API, return actual data
      For now, return dummy link to minutes if minutes_published is true
       */
      if ($node->get('field_meeting_minutes_published')->value) {
        $transformedResult['minutes_link'] = $this->getMeetingMinutesUrlFromEntity($node);
      }

      $result[$date][] = $transformedResult;
    }

    return $result;
  }

  /**
   * Get the previous scheduled meeting.
   *
   * @param string $id
   *   Policymaker id.
   *
   * @return string|void
   *   Meeting date as string if found
   */
  public function previousMeetingDate(string $id) {
    $queryResult = $this->query([
      'to' => date('Y-m-d', strtotime('now')),
      'limit' => 1,
      'policymaker' => $id,
      'sort' => 'DESC',
    ]);

    if (!empty($queryResult)) {
      $meeting = reset($queryResult);
      $meeting = reset($meeting);
      return $meeting['meeting_date'];
    }
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
    $queryResult = $this->query([
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
   * Get URL to meeting minutes document based on meeting ID.
   *
   * @param string $id
   *   Meeting ID.
   *
   * @return string|null
   *   URL to meeting minutes document, if one exists.
   */
  public function getMeetingMinutesUrl(string $id): ?string {
    $query = \Drupal::entityQuery('node')
      ->condition('status', 1)
      ->condition('type', self::NODE_TYPE)
      ->condition('field_meeting_id', $id);

    $ids = $query->execute();

    if (empty($ids)) {
      return NULL;
    }

    $entity = Node::load(reset($ids));
    if (!$entity instanceof NodeInterface) {
      return NULL;
    }

    return $this->getMeetingMinutesUrlFromEntity($entity);
  }

  /**
   * Undocumented function
   *
   * @param Drupal\node\NodeInterface $entity
   *   Meeting entity.
   *
   * @return string|null
   *   URL for document, if possible to get.
   */
  public function getMeetingMinutesUrlFromEntity(NodeInterface $entity): ?string {
    $minutes_document = $this->getMeetingMinutesFromEntity($entity);

    if ($minutes_document) {
      $minutes_url = $this->getUrlFromAhjoDocument($minutes_document);
    }

    if ($minutes_url) {
      return $minutes_url;
    }

    return NULL;
  }

  /**
   * Get meeting minutes document from meeting node.
   *
   * @param Drupal\node\NodeInterface $entity
   *   Meeting entity.
   *
   * @return Drupal\media\MediaInterface|null
   *   Meeting minutes media entity, if one exists for the meeting.
   */
  private function getMeetingMinutesFromEntity(NodeInterface $entity): ?MediaInterface {
    if (!$entity->hasField('field_meeting_documents') || $entity->get('field_meeting_documents')->isEmpty()) {
      return NULL;
    }

    $minutes_id = NULL;
    foreach ($entity->get('field_meeting_documents') as $field) {
      $json = json_decode($field->value, TRUE);
      if (isset($json['Type']) && isset($json['NativeId']) && $json['Type'] === 'pöytäkirja') {
        $minutes_id = $json['NativeId'];
        break;
      }
    }

    if (!$minutes_id) {
      return NULL;
    }

    $query = \Drupal::entityQuery('media')
      ->condition('status', 1)
      ->condition('bundle', self::DOCUMENT_TYPE)
      ->condition('field_document_native_id', $minutes_id);

    $mids = $query->execute();

    if (empty($mids)) {
      return NULL;
    }

    $document = Media::load(reset($mids));
    if (!$document instanceof MediaInterface) {
      return NULL;
    }

    return $document;
  }

  /**
   * Get URL from AHJO document.
   *
   * @param Drupal\media\MediaInterface $entity
   *   Ahjo Document to get URL for.
   *
   * @return string|null
   *   URL for document, if possible to get.
   */
  private function getUrlFromAhjoDocument(MediaInterface $entity): ?string {
    $uri_field = $entity->get('field_document_uri')->first();
    if (!$uri_field) {
      return NULL;
    }

    $url = $uri_field->getUrl();

    if ($url) {
      return $url->toString();
    }

    return NULL;
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
