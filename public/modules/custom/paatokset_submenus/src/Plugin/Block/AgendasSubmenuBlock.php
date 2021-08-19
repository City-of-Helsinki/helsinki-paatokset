<?php

namespace Drupal\paatokset_submenus\Plugin\Block;

use Drupal\Core\Block\BlockBase;

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
   * PolicymakerService instance.
   *
   * @var Drupal\paatokset_ahjo\Service\PolicymakerService
   */
  private $policymakerService;

  /**
   * Class constructor.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->policymakerService = \Drupal::service('Drupal\paatokset_ahjo\Service\PolicymakerService');
  }

  /**
   * Build the attributes.
   */
  public function build() {
    $years = $this->policymakerService->getAgendasYears();
    $list = $this->policymakerService->getAgendasList();

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

}
