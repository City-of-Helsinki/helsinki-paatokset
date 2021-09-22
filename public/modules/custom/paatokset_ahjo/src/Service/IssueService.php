<?php

namespace Drupal\paatokset_ahjo\Service;

use Drupal\Core\Session\AccountInterface;
use Drupal\paatokset_ahjo\Entity\AgendaItem;
use Drupal\paatokset_ahjo\Entity\Attachment;
use Drupal\paatokset_ahjo\Entity\Issue;
use Drupal\paatokset_ahjo\Entity\Meeting;

/**
 * Service class for retrieving issue-related data.
 *
 * @package Drupal\paatokset_ahjo\Services
 */
class IssueService {

  /**
   * Current Drupal user.
   *
   * @var currentUser
   */
  protected $currentUser;

  /**
   * Currently selected decision.
   *
   * @var selectedHandling
   */
  private $selectedHandling;

  /**
   * Current issue entity.
   *
   * @var entity
   */
  private $entity;

  /**
   * CustomService constructor.
   *
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   Current Drupal User.
   */
  public function __construct(AccountInterface $currentUser) {
    $this->currentUser = $currentUser;
    $this->selectedHandling = \Drupal::request()->query->get('decision');
    $entityTypeIndicator = \Drupal::routeMatch()->getParameters()->keys()[0];
    $entity = \Drupal::routeMatch()->getParameter($entityTypeIndicator);
    if (!is_object($entity)) {
      $entity = Issue::load($entity);
    }
    $this->entity = $entity;
  }

  /**
   * Return issue-related data and agenda item-related attachments.
   *
   * @return array
   *   Queried data
   */
  public function getData() {
    $handlings = $this->getMasterQuery($this->entity->get('id')->value);
    if (count($handlings) > 0) {
      $currentHandlingKey = 0;
      if ($this->selectedHandling) {
        $currentHandlingKey = array_search($this->selectedHandling, array_column($handlings, 'id'));
      }
      $currentHandling = $handlings[$currentHandlingKey];

      $currentAgendaItem = AgendaItem::load($currentHandling['link']);
      $attachments = $this->getAttachments($currentHandling['resource_uri']);

      if ($currentHandlingKey > 0 && array_key_exists((string) $currentHandlingKey - 1, $handlings)) {
        $nextHandling = $handlings[$currentHandlingKey - 1];
      }
      if (array_key_exists((string) $currentHandlingKey + 1, $handlings)) {
        $previousHandling = $handlings[$currentHandlingKey + 1];
      }
      $allDecisionsLink = $this->getMeetingUrl($currentAgendaItem->get('meeting_id')->value);

      $confidentialityReasons = [];
      if ($attachments && count($attachments) > 0) {
        foreach ($attachments as $attachment) {
          if (!$attachment->get('public')->value && $attachment->get('confidentiality_reason')->value) {
            $confidentialityReasons[] = $attachment->get('confidentiality_reason');
          }
        }
      }
    }

    return [
      'handlings' => $handlings,
      'current_handling' => $currentHandling ?? NULL,
      'current_agenda_item' => $currentAgendaItem ?? NULL,
      'attachments' => $attachments ?? [],
      'confidentiality_reasons' => $confidentialityReasons ?? [],
      'next_handling' => $nextHandling ?? FALSE,
      'previous_handling' => $previousHandling ?? FALSE,
      'all_decisions_link' => $allDecisionsLink ?? NULL,
    ];
  }

  /**
   * Return current Issue's diarynumber.
   *
   * @return string
   *   Diarynumber value
   */
  public function getDiaryNumber() {
    return $this->entity->get('diarynumber')->value;
  }

  /**
   * Get all issue-related data in one query.
   *
   * @param string|int $issueId
   *   Id value of the issue.
   *
   * @return array
   *   Raw values from the query
   */
  private function getMasterQuery($issueId) {
    $database = \Drupal::database();
    $query = $database->select('paatokset_meeting_field_data', 'pmfd')
      ->fields('pmfd', ['meeting_date', 'policymaker'])
      ->fields('paifd', ['id', 'resource_uri'])
      ->fields('nfot', ['field_organization_type_value']);
    $query->addField('pmfd', 'id', 'meeting_id');
    $query->join('paatokset_agenda_item_field_data', 'paifd', 'pmfd.id = paifd.meeting_id');
    $query->join('node__field_resource_uri', 'nfru', 'nfru.field_resource_uri_value = pmfd.policymaker_uri');
    $query->join('node__field_organization_type', 'nfot', 'nfot.entity_id = nfru.entity_id');
    $query->condition('paifd.issue_id', $issueId);
    $query->orderBy('pmfd.meeting_date', 'DESC');
    $results = $query->execute()->fetchAll();

    $transformed_result = [];
    foreach ($results as $result) {
      $date = date('d.m.Y', strtotime($result->meeting_date));
      $transformed_result[] = [
        'title' => "$result->policymaker/$date",
        'class' => str_replace('_', '-', $result->field_organization_type_value),
        'link' => $result->id,
        'id' => $result->id,
        'policymaker_name' => $result->policymaker,
        'date' => $result->meeting_date,
        'resource_uri' => $result->resource_uri,
      ];
    }

    return $transformed_result;
  }

  /**
   * Query attachments related to agenda item.
   *
   * @param string|int $agendaItemUri
   *   Uri value for agenda item.
   *
   * @return array
   *   Ids of attachments related to agenda items
   */
  private function getAttachments($agendaItemUri) {
    $attachmentIds = \Drupal::entityQuery('paatokset_attachment')->condition('agenda_item_url', $agendaItemUri)->execute();
    return count($attachmentIds) > 0 ? $attachments = Attachment::loadMultiple($attachmentIds) : [];
  }

  /**
   * Get the URL for agenda item's meeting.
   *
   * @param string|int $meetingId
   *   ID value for meeting.
   *
   * @return string
   *   Return the URL
   */
  private function getMeetingUrl($meetingId) {
    $meeting = Meeting::load($meetingId);
    return $meeting ? $meeting->toUrl()->toString() : NULL;
  }

}
