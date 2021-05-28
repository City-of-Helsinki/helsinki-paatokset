<?php

namespace Drupal\paatokset_decisions_submenu\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Url;

/**
 * Provides Decisions Submenu Block.
 *
 * @Block(
 *    id = "decisions_submenu",
 *    admin_label = @Translation("Decisions Submenu"),
 *    category = @Translation("Paatokset custom blocks")
 * )
 */
class DecisionsSubmenuBlock extends BlockBase {

  /**
   * @{inheritdoc}
   */
  public function build() {
    return [
      '#attributes' => [
        '#title' => $this->getCurrentNodeTitle(),
        '#tree' => $this->getDecisionTree(),
      ],
    ];
  }

  /**
   *
   */
  private function getCurrentNodeTitle() {
    return \Drupal::routeMatch()->getParameter('node')->getTitle();
  }

  /**
   *
   */
  private function getDecisionTree() {
    $database = \Drupal::database();
    $query = $database->select('paatokset_agenda_item_field_data', 'p')
      ->fields('p', ['classification_description']);
    $query->addExpression('YEAR(FROM_UNIXTIME(created))', 'created_year');
    $query->groupBy('classification_description');
    $query->groupBy('created_year');

    $queryResult = $query->execute();
    $result = [];

    foreach ($queryResult as $row) {
      $result[$row->classification_description][] = [
        '#type' => 'link',
        '#title' => $row->created_year,
        '#url' => Url::fromRoute('entity.node.canonical', ['node' => 1]),
      ];
    }

    return $result;
  }

}
