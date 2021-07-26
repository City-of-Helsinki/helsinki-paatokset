<?php

namespace Drupal\paatokset_submenus\Services;

use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;

/**
 * Service class for retrieving Agenda Item-related data.
 *
 * @package Drupal\paatokset_submenus\Services
 */
class AgendaItemService {
  /**
   * Policymaker node's ID.
   *
   * @var id
   */
  private $id;

  /**
   * Policymaker URI, used as id in queries.
   *
   * @var link
   */
  private $link;

  /**
   * Class constructor.
   */
  public function __construct() {
    $this->setIds();
  }

  /**
   * Get all the decisions for one policymaker id.
   *
   * @return array
   *   of results.
   */
  public function getAgendasYears(): array {
    // We need to get a link so we can get the
    // right agenda items, since we dont have the plain ID in agenda items.
    $database = \Drupal::database();
    $query = $database->select('paatokset_agenda_item_field_data', 'aifd')
      ->condition('meeting_policymaker_link', $this->link);
    $query->fields('aifd', ['meeting_policymaker_link']);
    $query->addExpression('YEAR(meeting_date)', 'date');
    $query->groupBy('date');
    $query->orderBy('date', 'DESC');
    $queryResult = $query->distinct()->execute()->fetchAll();
    $result = [];
    foreach ($queryResult as $row) {
      $result[$row->date][] = [
        '#type' => 'link',
        '#title' => $row->date,
      ];
    }

    return $result;
  }

  /**
   * Get all the decisions for one classification code.
   *
   * @return array
   *   of results.
   */
  public function getAgendasList(): array {
    $database = \Drupal::database();
    $query = $database->select('paatokset_agenda_item_field_data', 'aifd')
      ->fields('aifd', ['subject', 'meeting_date', 'meeting_number']);
    $query->addExpression('YEAR(meeting_date)', 'year');
    $query->condition('meeting_policymaker_link', $this->link, '=');
    $query->orderBy('meeting_date', 'DESC');
    $queryResult = $query->execute()->fetchAll();
    $result = [];
    foreach ($queryResult as $row) {
      $result[$row->year][] = [
        '#type' => 'link',
        '#date' => date("d.m.Y", strtotime($row->meeting_date)),
        '#timestamp' => strtotime($row->meeting_date),
        '#meetingNumber' => $row->meeting_number,
        '#responsiveDate' => date("m-Y", strtotime($row->meeting_date)),
        '#responsiveTitle' => 'Pöytäkirja',
        '#year' => $row->year,
        '#title' => $row->subject,
        '#url' => Url::fromUri('internal:' . \Drupal::request()->getRequestUri()),
      ];
    }
    return $result;
  }

  /**
   * Get all meeting document-related data.
   *
   * @return array
   *   Array containing queried data
   */
  public function getMinutesOfDiscussion() : array {
    $database = \Drupal::database();
    $query = $database->select('paatokset_meeting_field_data', 'pmfd')
      ->fields('pmfd', ['id', 'meeting_date']);
    $query->orderBy('meeting_date', 'DESC');
    $query->condition('policymaker_uri', $this->link, '=');
    $result = $query->execute()->fetchAllKeyed();
    $mediaEntities = $this->getMeetingMediaEntities(array_keys($result));
    $list = [];
    $years = [];
    foreach ($mediaEntities as $id => $meeting) {
      foreach ($meeting as $entity) {
        $file_id = $entity->get('field_document')->target_id;
        if ($entity->get('field_document')->target_id) {
          $download_link = Url::fromUri(file_create_url(File::load($file_id)->getFileUri()));
        }
        $year = date('Y', strtotime($result[$id]));
        $title = t('Valtuuston kokous') . ' ' . date("d.m.Y", strtotime($result[$id]));
        $list[$year][] = [
          '#type' => 'link',
          '#responsiveDate' => date("m-Y", strtotime($result[$id])),
          '#responsiveTitle' => $title,
          '#date' => date("d.m.Y", strtotime($result[$id])),
          '#timestamp' => strtotime($result[$id]),
          '#year' => $year,
          '#title' => $title,
          '#url' => '',
          '#download_link' => $download_link ?? NULL,
          '#download_label' => str_replace(' ', '_', $title),
        ];

        if (!isset($years[$year])) {
          $years[$year][] = [
            '#type' => 'link',
            '#title' => $year,
          ];
        }
      }
    }

    return [
      'years' => $years,
      'list' => $list,
    ];
  }

  /**
   * Get all policymaker documents.
   *
   * @return array
   *   Array of resulting documents
   */
  public function getDocumentData() : array {
    $documents = $this->getMinutesOfDiscussion();
    $declarationsOfAffiliation = $this->getDeclarationsOfAffilition();

    foreach ($declarationsOfAffiliation as $declaration) {
      $date = $declaration->get('created')->value;
      $title = $declaration->name->value;
      $year = date('Y', $date);
      if (!isset($documents['years'])) {
        $documents['years'][$years] = [
          '#type' => 'link',
          '#title' => $year,
        ];
      }

      $file_id = $declaration->get('field_document')->target_id;
      if ($declaration->get('field_document')->target_id) {
        $download_link = Url::fromUri(file_create_url(File::load($file_id)->getFileUri()));
      }

      $documents['list'][$year][] = [
        '#type' => 'link',
        '#responsiveDate' => date("m-Y", $date),
        '#responsiveTitle' => $title,
        '#date' => date("d.m.Y", $date),
        '#timestamp' => $date,
        '#year' => $year,
        '#title' => $title,
        '#url' => '',
        '#download_link' => $download_link ?? NULL,
        '#download_label' => str_replace(' ', '_', $title),
      ];
    }

    return $documents;
  }

  /**
   * Get policymaker-related declarations of affiliation.
   *
   * @return array
   *   Array of resulting documents
   */
  private function getDeclarationsOfAffilition() {
    $ids = \Drupal::entityQuery('media')
      ->condition('bundle', 'declaration_of_affiliation')
      ->condition('field__policymaker_reference', $this->id)
      ->execute();

    return Media::loadMultiple($ids);
  }

  /**
   * Get meeting-related documents.
   *
   * @return array
   *   Array of resulting documents
   */
  private function getMeetingMediaEntities($meetingids) {
    if (count($meetingids) === 0) {
      return [];
    }

    $ids = \Drupal::entityQuery('media')
      ->condition('bundle', 'minutes_of_the_discussion')
      ->condition('field_meetings_reference', $meetingids, 'IN')
      ->execute();
    $entities = Media::loadMultiple($ids);

    $result = [];
    foreach ($entities as $entity) {
      $meeting_id = $entity->get('field_meetings_reference')->target_id;
      $result[$meeting_id][] = $entity;
    }

    return $result;
  }

  /**
   * Get policymaker id / node id to use in queries.
   */
  private function setIds() : void {
    $node = \Drupal::routeMatch()->getParameter('node');
    if ($node && $node->hasField('field_policymaker_id') && !$node->get('field_policymaker_id')->isEmpty()) {
      $this->id = (int) $node->id();
      $this->link = '/paatokset/v1/policymaker/' . $node->field_policymaker_id->value . '/';
    }
    if (!$node && preg_match('/view\./', \Drupal::routeMatch()->getRouteName())) {
      $current_path = \Drupal::service('path.current')->getPath();
      $path_parts = explode('/', $current_path);
      array_pop($path_parts);
      $path_alias = implode('/', $path_parts);
      $path = \Drupal::service('path_alias.manager')->getPathByAlias($path_alias);
      if (preg_match('/node\/(\d+)/', $path, $matches)) {
        $node = Node::load($matches[1]);
        $this->id = (int) $node->id();
        $this->link = '/paatokset/v1/policymaker/' . $node->field_policymaker_id->value . '/';
      }
    }
  }

}
