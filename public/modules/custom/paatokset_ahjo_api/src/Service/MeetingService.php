<?php

namespace Drupal\paatokset_ahjo_api\Service;

use Drupal\Core\Link;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\Core\Utility\Error;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\search_api\Entity\Index;

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

    $index = Index::load('meetings');
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
      Error::logException(\Drupal::logger('paatokset_ahjo_api'), $exception);
      return [];
    }

    $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();

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
        $additional_info = $this->t('Meeting moved, original time: @orig_time', [
          '@orig_time' => date('d.m. H:i', $orig_timestamp),
        ]);
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
      ->accessCheck(TRUE)
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

    if (isset($params['not_cancelled'])) {
      $query->condition('field_meeting_status', 'peruttu', '<>');
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

    $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();

    $result = [];
    foreach (Node::loadMultiple($ids) as $node) {
      $timestamp = $node->get('field_meeting_date')->date->getTimeStamp();
      $date = date('Y-m-d', $timestamp);

      $meeting_cancelled = FALSE;
      if ($node->get('field_meeting_status')->value === 'peruttu') {
        $meeting_cancelled = TRUE;
      }

      if ($meeting_cancelled && isset($params['only_future_cancelled'])) {
        $now = strtotime('now');
        if ($meeting_cancelled && $timestamp < $now) {
          continue;
        }
      }

      // Check if meeting was moved.
      $orig_timestamp = NULL;
      if ($node->hasField('field_meeting_date_original') && !$node->get('field_meeting_date_original')->isEmpty()) {
        $orig_timestamp = $node->get('field_meeting_date_original')->date->getTimeStamp();
      }

      $additional_info = NULL;
      $meeting_moved = FALSE;
      if ($meeting_cancelled) {
        $additional_info = $this->t('Meeting cancelled');
      }
      elseif ($orig_timestamp && $orig_timestamp !== $timestamp) {
        $additional_info = $this->t('Meeting moved, original time: @orig_time', [
          '@orig_time' => date('d.m. H:i', $orig_timestamp),
        ]);
        $meeting_moved = TRUE;
      }

      $transformedResult = [
        'title' => $node->get('title')->value,
        'meeting_date' => $timestamp,
        'meeting_moved' => $meeting_moved,
        'meeting_cancelled' => $meeting_cancelled,
        'policymaker_type' => $this->getPolicymakerType($node->get('field_meeting_dm')->value),
        'policymaker_name' => $this->getPolicymakerName($node->get('field_meeting_dm_id')->value, $node->get('field_meeting_dm')->value, $langcode),
        'policymaker' => $node->get('field_meeting_dm_id')->value,
        'start_time' => date('H:i', $timestamp),
        'orig_time' => date('d.m. H:i', $orig_timestamp),
        'status' => $node->get('field_meeting_status')->value,
        'additional_info' => $additional_info,
      ];

      if (!$meeting_cancelled && $node->get('field_meeting_minutes_published')->value) {
        $transformedResult['minutes_link'] = $this->getMeetingUrl($node);
      }
      elseif (!$meeting_cancelled && $node->get('field_meeting_agenda_published')->value && !$node->get('field_meeting_decision')->isEmpty()) {
        $transformedResult['decision_link'] = $this->getMeetingUrl($node);
      }
      elseif (!$meeting_cancelled && $node->get('field_meeting_agenda_published')->value) {
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
   * Get policymaker name for meeting.
   *
   * @param string $id
   *   Policymaker ID.
   * @param string $default_name
   *   Default policymaker name if node can't be loaded.
   * @param string $langcode
   *   Which translation of policymaker node to get.
   *
   * @return string
   *   Policymaker name.
   */
  private function getPolicymakerName(string $id, string $default_name, string $langcode = 'fi'): string {
    /** @var \Drupal\paatokset_policymakers\Service\PolicymakerService $policymakerService */
    $policymakerService = \Drupal::service('paatokset_policymakers');

    $name = $policymakerService->getPolicymakerNameById($id, $langcode, FALSE);
    if ($name !== NULL) {
      return $name;
    }
    return $default_name;
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

    $text = $this->t('Meeting minutes.');

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
      return $url->toString(TRUE)->getGeneratedUrl();
    }

    return NULL;
  }

  /**
   * Get Meeting URL.
   *
   * @param string $meeting_id
   *   Meeting ID.
   * @param string $policymaker_id
   *   Policymaker ID.
   * @param bool $include_anchor
   *   Include decision announcement anchor.
   *
   * @return string|null
   *   URL as string, if possible to get.
   */
  public function getMeetingUrlWithoutNode(string $meeting_id, string $policymaker_id, bool $include_anchor = FALSE) : ?string {
    /** @var \Drupal\paatokset_policymakers\Service\PolicymakerService $policymakerService */
    $policymakerService = \Drupal::service('paatokset_policymakers');
    $url = $policymakerService->getMinutesRoute($meeting_id, $policymaker_id, $include_anchor);
    if ($url instanceof Url) {
      return $url->toString(TRUE)->getGeneratedUrl();
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
      ->accessCheck(TRUE)
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

    if (!$document) {
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
   * @param string|null $langcode
   *   Document language.
   *
   * @return array|null
   *   Meeting minutes JSON, if one exists for the meeting.
   */
  public function getDocumentFromEntity(NodeInterface $entity, string $document_type, ?string $langcode = NULL): ?array {
    if (!$entity->hasField('field_meeting_documents') || $entity->get('field_meeting_documents')->isEmpty()) {
      return NULL;
    }

    foreach ($entity->get('field_meeting_documents') as $field) {
      $json = json_decode($field->value, TRUE);
      if ($langcode && isset($json['Language']) && !str_contains($json['Language'], $langcode)) {
        continue;
      }

      if (isset($json['Type']) && isset($json['NativeId']) && $json['Type'] === $document_type) {
        return $json;
      }
    }

    return NULL;
  }

  /**
   * Get URL from AHJO document.
   *
   * @param array $document
   *   Ahjo Document to get URL for (array decoded from JSON).
   *
   * @return string|null
   *   URL for document, if possible to get.
   */
  public function getUrlFromAhjoDocument(array $document): ?string {
    if (!empty($document['FileURI'])) {
      return $document['FileURI'];
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
