<?php

namespace Drupal\paatokset_ahjo\Service;

use Drupal\Core\Session\AccountInterface;
use Drupal\paatokset_ahjo\Entity\AgendaItem;
use Drupal\paatokset_ahjo\Entity\Attachment;
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
   * CustomService constructor.
   *
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   Current Drupal User.
   */
  public function __construct(AccountInterface $currentUser) {
    $this->currentUser = $currentUser;
  }

  /**
   * Return issue-related data and agenda item-related attachments.
   *
   * @param string|int $issueId
   *   Id value of the issue.
   *
   * @return array
   *   Queried data
   */
  public function getData($issueId) {
    $handlings = $this->getMasterQuery($issueId);
    if (count($handlings) > 0) {
      $currentHandling = $handlings[0];
      $currentAgendaItem = AgendaItem::load($currentHandling['link']);
      $attachments = $this->getAttachments($currentHandling['resource_uri']);
      $hasNextHandling = $currentHandling['date'] < $handlings[0]['date'];
      $hasPreviousHandling = $currentHandling['date'] > $handlings[count($handlings) - 1]['date'];
      $allDecisionsLink = $this->getMeetingUrl($currentAgendaItem->get('meeting_id')->value);
    }

    return [
      'handlings' => $handlings,
      'current_handling' => $currentHandling ?? NULL,
      'current_agenda_item' => $currentAgendaItem ?? NULL,
      'attachments' => $attachments,
      'has_next_handling' => $hasNextHandling ?? FALSE,
      'has_previous_handling' => $hasPreviousHandling ?? FALSE,
      'all_decisions_link' => $allDecisionsLink,
    ];
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
