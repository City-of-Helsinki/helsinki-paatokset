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

  /**
   * Build the attributes.
   */
  public function build() {
    return [
      '#cache' => ['contexts' => ['url.path', 'url.query_args']],
      '#title' => 'Viranhaltijapäätökset',
      '#years' => $this->getAgendasYears(),
      '#list' => $this->getAgendasList()
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
    $link = '/paatokset/v1/policymaker/' . $this->getPolicymakerId() . '/';
    $database = \Drupal::database();
    $query = $database->select('paatokset_agenda_item_field_data', 'aifd')
      ->condition('meeting_policymaker_link', $link);
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

    // We need to get a link so we can get the
    // right agenda items, since we dont have the plain ID in agenda items.
    $link = '/paatokset/v1/policymaker/' . $this->getPolicymakerId() . '/';
    $database = \Drupal::database();
    $query = $database->select('paatokset_agenda_item_field_data', 'aifd')
      ->fields('aifd', ['subject', 'meeting_date']);
    $query->addExpression('YEAR(meeting_date)', 'year');
    $query->condition('meeting_policymaker_link', $link, '=');
    $query->orderBy('meeting_date', 'DESC');
    $queryResult = $query->execute()->fetchAll();
    $result = [];
    foreach ($queryResult as $row) {
      $result[$row->year][] = [
        '#type' => 'link',
        '#date' => date("d.m.Y", strtotime($row->meeting_date)),
        '#year' => $row->year,
        '#title' => $row->subject,
        '#url' => Url::fromUri('internal:' . \Drupal::request()->getRequestUri()),
      ];
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
    if ($node->hasField('field_policymaker_id') && !$node->get('field_policymaker_id')->isEmpty()) {
      return (int) $node->field_policymaker_id->value;
    }
    return NULL;
  }

}
