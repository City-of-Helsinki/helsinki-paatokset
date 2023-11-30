<?php

namespace Drupal\paatokset_submenus\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\paatokset_policymakers\Service\PolicymakerService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides Agendas Submenu Documents Block.
 *
 * @Block(
 *    id = "paatokset_minutes_of_discussion",
 *    admin_label = @Translation("Paatokset minutes of discussion block"),
 *    category = @Translation("Paatokset custom blocks")
 * )
 */
class MinutesOfDiscussionBlock extends BlockBase implements ContainerFactoryPluginInterface {

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
