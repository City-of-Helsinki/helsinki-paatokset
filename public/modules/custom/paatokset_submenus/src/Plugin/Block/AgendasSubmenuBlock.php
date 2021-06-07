<?php

namespace Drupal\paatokset_submenus\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Url;

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

  /*
   * Build the attributes.
   */
  public function build() {
    return [
      '#attributes' => [
        '#title' => 'Viranhaltijapäätökset',
        '#tree' => $this->getDecisionTree(),
      ],
    ];
  }

  /*
   * Get classification code so we can search with it.
   */
  private function getClassificationCode() {

    $id = \Drupal::routeMatch()->getRawParameter('paatokset_agenda_item');
    $database = \Drupal::database();
    $query = $database->select('paatokset_agenda_item_field_data', 'aifd')
      ->fields('aifd', ['classification_code'])
      ->condition('id', $id);
    $code = $query->execute()->fetchObject();
    return $code->classification_code;
  }

  /*
   * Get all the decisions for one classification code.
   */
  private function getDecisionTree() {
    $database = \Drupal::database();
    $query = $database->select('paatokset_agenda_item_field_data', 'p')
      ->fields('p', ['classification_description'])
      ->condition('classification_code', $this->getClassificationCode());
    $query->addExpression('YEAR(FROM_UNIXTIME(created))', 'created_year');
    $query->groupBy('classification_description');
    $query->groupBy('created_year');

    $queryResult = $query->execute()->fetchAll();
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
