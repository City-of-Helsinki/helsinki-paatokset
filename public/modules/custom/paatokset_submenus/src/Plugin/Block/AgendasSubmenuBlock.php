<?php

namespace Drupal\paatokset_submenus\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Url;
use phpDocumentor\Reflection\Types\Integer;

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
      '#attributes' => [
        '#title' => 'Viranhaltijapäätökset',
        '#tree' => $this->getAgendasTree(),
      ],
    ];
  }
  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    // If you need to redefine the Max Age for that block
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return ['url.path', 'url.query_args'];
  }
  /**
   * Get all the decisions for one classification code.
   */
  private function getAgendasTree(): array {

    // We need to get a link so we can get teh right agenda items, since we dont have the plain ID in agenda items
    $link = '/paatokset/v1/policymaker/' . $this->getPolicymakerID() . '/';
    $database = \Drupal::database();
    $query = $database->select('paatokset_agenda_item_field_data', 'aifd')
      ->fields('aifd', ['meeting_policymaker_link'])
      ->condition('meeting_policymaker_link', $link);
    $query->addExpression('YEAR(meeting_date)', 'date');
    $query->groupBy('meeting_policymaker_link');
    $query->groupBy('date');
    $query->orderBy('date', 'DESC');

    $queryResult = $query->execute()->fetchAll();
    $result = [];

    foreach ($queryResult as $row) {
      $result[$row->meeting_policymaker_link][] = [
        '#type' => 'link',
        '#title' => $row->date,
        '#url' => Url::fromUri('internal:' . \Drupal::request()->getRequestUri()),
      ];
    }
    return $result;
  }

  /**
   * Get policymaker code so we can search with it.
   * @return int
   */
  private function getPolicymakerID(): int
  {
    return intval(\Drupal::routeMatch()->getRawParameter('node'));
  }

}
