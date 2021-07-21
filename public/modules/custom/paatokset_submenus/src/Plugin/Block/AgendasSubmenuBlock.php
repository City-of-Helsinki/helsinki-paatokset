<?php

namespace Drupal\paatokset_submenus\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;

/**
 * Provides Agendas Submenu Block.
 *
 * @Block(
 *    id = "agendas_submenu",
 *    admin_label = @Translation("Agendas Submenu"),
 *    category = @Translation("Paatokset custom blocks")
 * )
 */
class AgendasSubmenuBlock extends BlockBase {

  /**
   * Policymaker URI, used as id in queries.
   *
   * @var link
   */
  private $link;

  /**
   * Calculate and store $link.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->link = '/paatokset/v1/policymaker/' . $this->getPolicymakerId() . '/';
  }

  /**
   * Build the attributes.
   */
  public function build() {
    // @todo After left menu is implemented, read URL to determine what data to query
    if (FALSE) {
      $data = $this->getDocumentData();
      $years = $data['years'];
      $list = $data['list'];
    }
    else {
      $years = $this->getAgendasYears();
      $list = $this->getAgendasList();
    }

    return [
      '#cache' => ['contexts' => ['url.path', 'url.query_args']],
      '#title' => 'Viranhaltijapäätökset',
      '#years' => $years,
      '#list' => $list,
    ];
  }

  /**
   * Set cache age to zero.
   */
  public function getCacheMaxAge() {
    // If you need to redefine the Max Age for that block.
    return 0;
  }

  /**
   * Get cache contexts.
   */
  public function getCacheContexts() {
    return ['url.path', 'url.query_args'];
  }

  /**
   * Get all the decisions for one policymaker id.
   *
   * @return array
   *   of results.
   */
  private function getAgendasYears(): array {
    // We need to get a link so we can get the
    // right agenda items, since we dont have the plain ID in agenda items.
    $policymaker_id = $this->getPolicymakerId();
    if (!$policymaker_id) {
      return [];
    }
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
  private function getAgendasList(): array {
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
  private function getDocumentData() : array {
    $database = \Drupal::database();
    $query = $database->select('paatokset_meeting_field_data', 'pmfd')
      ->fields('pmfd', ['id', 'meeting_date']);
    $query->orderBy('meeting_date', 'DESC');
    $query->condition('policymaker_uri', $this->link, '=');
    $result = $query->execute()->fetchAllKeyed();
    $mediaEntities = $this->getMediaEntities(array_keys($result));
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
   * Get meeting-related documents.
   *
   * @return array|null
   *   Array of resulting documents
   */
  private function getMediaEntities($meetingids) {
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
   * Get policymaker code so we can search with it.
   *
   * @return int
   *   as the policymaker ID.
   */
  private function getPolicymakerId(): ?int {
    $node = \Drupal::routeMatch()->getParameter('node');
    if ($node && $node->hasField('field_policymaker_id') && !$node->get('field_policymaker_id')->isEmpty()) {
      return (int) $node->field_policymaker_id->value;
    }
    return NULL;
  }

}
