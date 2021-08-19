<?php

namespace Drupal\paatokset_submenus\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides Agendas Submenu Documents Block.
 *
 * @Block(
 *    id = "paatokset_minutes_of_discussion",
 *    admin_label = @Translation("Paatokset minutes of discussion block"),
 *    category = @Translation("Paatokset custom blocks")
 * )
 */
class MinutesOfDiscussionBlock extends BlockBase {
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
    $data = $this->policymakerService->getMinutesOfDiscussion();

    return [
      '#cache' => ['contexts' => ['url.path', 'url.query_args']],
      '#title' => 'Viranhaltijapäätökset',
      '#years' => $data['years'],
      '#list' => $data['list'],
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
