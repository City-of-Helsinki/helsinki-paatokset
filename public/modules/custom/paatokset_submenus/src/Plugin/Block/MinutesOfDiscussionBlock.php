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
   * @var Drupal\paatokset_policymakers\Service\PolicymakerService
   */
  private $policymakerService;

  /**
   * Class constructor.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->policymakerService = \Drupal::service('paatokset_policymakers');
    $this->policymakerService->setPolicyMakerByPath();
  }

  /**
   * Build the attributes.
   */
  public function build() {
    $minutes = $this->policymakerService->getMinutesOfDiscussion(NULL, TRUE);

    return [
      '#years' => array_keys($minutes),
      '#list' => $minutes,
    ];
  }

  /**
   * Get cache tags.
   */
  public function getCacheTags() {
    return [
      'media_list:minutes_of_the_discussion',
      'node_list:meeting',
    ];
  }

  /**
   * Get cache contexts.
   */
  public function getCacheContexts() {
    return ['url.path', 'url.query_args'];
  }

}
