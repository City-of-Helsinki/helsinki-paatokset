<?php

namespace Drupal\paatokset_submenus\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Url;

/**
 * Provides Meetings Submenu Block.
 *
 * @Block(
 *    id = "meetings_submenu",
 *    admin_label = @Translation("Meetings Submenu"),
 *    category = @Translation("Paatokset custom blocks")
 * )
 */
class MeetingsSubmenuBlock extends BlockBase {
  
  public function build() {
    return [
      '#attributes' => [
        '#title' => 'Esityslistat ja päätöspöytäkirjat',
        '#tree' => $this->getMeetingTree(),
      ],
    ];
  }

  /**
   * @todo we should use a policymaker ID and not just the name.
   */
  private function getPolicymaker() {

    $id = \Drupal::routeMatch()->getRawParameter('paatokset_meeting');
    $database = \Drupal::database();
    $query = $database->select('paatokset_meeting_field_data', 'aifd')
      ->fields('aifd', ['policymaker'])
      ->condition('id', $id);

    $code = $query->execute()->fetchObject();
    return $code->policymaker;
  }

  private function getMeetingTree() {
    $database = \Drupal::database();
    $query = $database->select('paatokset_meeting_field_data', 'pm')
      ->fields('pm', ['policymaker'])
      ->condition('policymaker', $this->getPolicymaker());
    $query->addExpression('YEAR(meeting_date)', 'meeting_date');
    $query->groupBy('meeting_date');
    $query->orderBy('meeting_date', 'DESC');
    $queryResult = $query->execute()->fetchAll();
    $result = [];

    foreach ($queryResult as $row) {
      $result[$row->policymaker][] = [
        '#type' => 'link',
        '#title' => $row->meeting_date,
        '#url' => Url::fromRoute('entity.node.canonical', ['node' => 1]),
      ];
    }

    return $result;
  }

}
