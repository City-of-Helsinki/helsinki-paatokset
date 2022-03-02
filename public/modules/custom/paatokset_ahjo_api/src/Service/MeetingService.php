<?php

namespace Drupal\paatokset_ahjo_api\Service;

use Drupal\media\MediaInterface;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;

/**
 * Service class for retrieving meeting-related data.
 *
 * @package Drupal\paatokset_ahjo_api\Services
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
        'policymaker_type' => $this->getPolicymakerType($node->get('field_meeting_dm')->value),
        'policymaker_name' => $node->get('field_meeting_dm')->value,
        'policymaker' => $node->get('field_meeting_dm_id')->value,
        'start_time' => date('H:i', $timestamp),
      ];

      if ($node->get('field_meeting_minutes_published')->value) {
        $transformedResult['minutes_link'] = $this->getMeetingUrl($node);
      }
      elseif ($node->get('field_meeting_agenda_published')->value && !$node->get('field_meeting_decision')->isEmpty()) {
        $transformedResult['decision_link'] = $this->getMeetingUrl($node);
      }
      elseif ($node->get('field_meeting_agenda_published')->value) {
        $transformedResult['motions_list_link'] = $this->getMeetingUrl($node);
      }

      $result[$date][] = $transformedResult;
    }

    return $result;
  }

  /**
   * Get policy maker type based on name.
   *
   * @param string $name
   *   Policy maker name.
   *
   * @return string
   *   Policy maker type CSS class.
   */
  private function getPolicymakerType(string $name): string {
    // @todo This should be refactored to fetch the actual type.
    $name = strtolower($name);
    if (strpos($name, 'valtuusto') !== FALSE) {
      return 'valtuusto';
    }
    if (strpos($name, 'toimikunta') !== FALSE) {
      return 'toimikunta';
    }
    if (strpos($name, 'lautakun') !== FALSE) {
      return 'lautakunta';
    }
    if (strpos($name, 'hallitus') !== FALSE) {
      return 'hallitus';
    }
    return 'trustee';
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
   * Get Meeting minutes Link.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Meeting node.
   *
   * @return \Drupal\Core\Link|null
   *   URL as string, if possible to get.
   */
  public function getMeetingLink(NodeInterface $node): ?Link {

    if (!$node->hasField('field_meeting_id') || !$node->hasField('field_meeting_dm_id')) {
      return NULL;
    }

    $meeting_id = $node->get('field_meeting_id')->value;
    $policymaker_id = $node->get('field_meeting_dm_id')->value;

    if (!$meeting_id || !$policymaker_id) {
      return NULL;
    }

    /** @var \Drupal\paatokset_policymakers\Service\PolicymakerService $policymakerService */
    $policymakerService = \Drupal::service('paatokset_policymakers');
    $url = $policymakerService->getMinutesRoute($meeting_id, $policymaker_id);
    if (!$url instanceof Url) {
      return NULL;
    }

    $text = t('Meeting minutes.');

    return Link::fromTextAndUrl($text, $url);
  }

  /**
   * Get Meeting minutes URL.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Meeting node.
   *
   * @return string|null
   *   URL as string, if possible to get.
   */
  public function getMeetingUrl(NodeInterface $node): ?string {
    /** @var \Drupal\paatokset_policymakers\Service\PolicymakerService $policymakerService */
    $policymakerService = \Drupal::service('paatokset_policymakers');

    $meeting_id = $node->get('field_meeting_id')->value;
    $policymaker_id = $node->get('field_meeting_dm_id')->value;
    $url = $policymakerService->getMinutesRoute($meeting_id, $policymaker_id);
    if ($url instanceof Url) {
      return $url->toString();
    }

    return NULL;
  }

  /**
   * Get URL to meeting minutes document based on meeting ID.
   *
   * @param string $id
   *   Meeting ID.
   * @param string $document_type
   *   Document type, for example: 'esityslista', 'pöytäkirja'.
   *
   * @return string|null
   *   URL to meeting minutes document, if one exists.
   */
  public function getMeetingsDocumentUrl(string $id, string $document_type): ?string {
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

    return $this->getDocumentUrlFromEntity($entity, $document_type);
  }

  /**
   * Get meeting minutes URL from meeting node.
   *
   * @param \Drupal\node\NodeInterface $entity
   *   Meeting entity.
   * @param string $document_type
   *   Document type, for example: 'esityslista', 'pöytäkirja'.
   *
   * @return string|null
   *   URL for document, if possible to get.
   */
  public function getDocumentUrlFromEntity(NodeInterface $entity, string $document_type): ?string {
    $document = $this->getDocumentFromEntity($entity, $document_type);

    if (!$document instanceof MediaInterface) {
      return NULL;
    }

    $document_url = $this->getUrlFromAhjoDocument($document);
    if ($document_url) {
      return $document_url;
    }

    return NULL;
  }

  /**
   * Get meeting minutes document from meeting node.
   *
   * @param Drupal\node\NodeInterface $entity
   *   Meeting entity.
   * @param string $document_type
   *   Document type, for example: 'esityslista', 'pöytäkirja'.
   * @param string $langcode
   *   Document language, defaults to 'fi'.
   *
   * @return \Drupal\media\MediaInterface|null
   *   Meeting minutes media entity, if one exists for the meeting.
   */
  public function getDocumentFromEntity(NodeInterface $entity, string $document_type, string $langcode = 'fi'): ?MediaInterface {
    if (!$entity->hasField('field_meeting_documents') || $entity->get('field_meeting_documents')->isEmpty()) {
      return NULL;
    }

    $minutes_id = NULL;
    foreach ($entity->get('field_meeting_documents') as $field) {
      $json = json_decode($field->value, TRUE);
      if (isset($json['Language']) && $json['Language'] !== $langcode) {
        continue;
      }

      if (isset($json['Type']) && isset($json['NativeId']) && $json['Type'] === $document_type) {
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
  public function getUrlFromAhjoDocument(MediaInterface $entity): ?string {
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
