<?php

namespace Drupal\paatokset_submenus\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\paatokset_submenus\Services\AgendaItemService;

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
   * AgendaItemService instance.
   *
   * @var agendaItemService
   */
  private $agendaItemService;

  /**
   * Class constructor.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->agendaItemService = \Drupal::getContainer()->get(AgendaItemService::class);
  }

  /**
   * Build the attributes.
   */
  public function build() {
    $data = $this->agendaItemService->getDocumentData();

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
