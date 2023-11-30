<?php

namespace Drupal\paatokset_submenus\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides Agendas Submenu Documents Block.
 *
 * @Block(
 *    id = "agendas_submenu_documents",
 *    admin_label = @Translation("Paatokset policymaker documents"),
 *    category = @Translation("Paatokset custom blocks")
 * )
 */
class DocumentsBlock extends BlockBase {
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
    $list = $this->policymakerService->getApiMinutes(NULL, TRUE);

    return [
      '#title' => $this->t('Office holder decisions'),
      '#years' => array_keys($list),
      '#list' => $list,
    ];
  }

  /**
   * Get cache tags.
   */
  public function getCacheTags() {
    return ['node_list:meeting'];
  }

  /**
   * Get cache contexts.
   */
  public function getCacheContexts() {
    return ['url.path', 'url.query_args'];
  }

}
