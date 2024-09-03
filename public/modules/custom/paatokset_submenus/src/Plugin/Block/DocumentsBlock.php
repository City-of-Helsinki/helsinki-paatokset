<?php

namespace Drupal\paatokset_submenus\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\node\NodeInterface;
use Drupal\paatokset_policymakers\Service\PolicymakerService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides Agendas Submenu Documents Block.
 *
 * @Block(
 *    id = "agendas_submenu_documents",
 *    admin_label = @Translation("Paatokset policymaker documents"),
 *    category = @Translation("Paatokset custom blocks")
 * )
 */
class DocumentsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritDoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private PolicymakerService $policymakerService,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->policymakerService->setPolicyMakerByPath();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('paatokset_policymakers')
    );
  }

  /**
   * Build the attributes.
   */
  public function build() {
    $list = $this->policymakerService->getApiMinutes(NULL, TRUE);
    // UHF-10527: Switch over to using elasticQuery.
    // phpcs:ignore
    //$list = $this->policymakerService->getApiMinutesFromElasticSearch(NULL, TRUE);

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
    $policymaker = $this->policymakerService->getPolicyMaker();
    if ($policymaker instanceof NodeInterface && $policymaker->hasField('field_policymaker_id')) {
      $policymaker_id = $policymaker->get('field_policymaker_id')->value;
      return ['meeting_pm:' . $policymaker_id];
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
