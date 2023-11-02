<?php

namespace Drupal\paatokset_submenus\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\node\NodeInterface;

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
    $list = $this->policymakerService->getAgendasListFromElasticSearch(NULL, TRUE);
    $years = array_keys($list);

    return [
      '#title' => 'Viranhaltijapäätökset',
      '#years' => $years,
      '#list' => $list,
    ];
  }

  /**
   * Get cache tags.
   */
  public function getCacheTags() {
    $policymaker = $this->policymakerService->getPolicyMaker();
    if ($policymaker instanceof NodeInterface && $policymaker->hasField('field_policymaker_id')) {
      $policymaker_id = $policymaker->get('field_policymaker_id')->value;
      return ['decision_pm:' . $policymaker_id];
    }
    return [];
  }

  /**
   * Get cache contexts.
   */
  public function getCacheContexts() {
    return ['url.path', 'url.query_args'];
  }

}
