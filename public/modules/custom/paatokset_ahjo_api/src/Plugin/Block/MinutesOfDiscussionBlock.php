<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\paatokset_policymakers\Service\PolicymakerService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides Agendas Submenu Documents Block.
 */
#[Block(
  id: 'paatokset_minutes_of_discussion',
  admin_label: new TranslatableMarkup('Paatokset minutes of discussion block'),
  category: new TranslatableMarkup('Paatokset custom blocks'),
)]
class MinutesOfDiscussionBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritDoc}
   */
  final public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly PolicymakerService $policymakerService,
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
   * {@inheritDoc}
   */
  public function build(): array {
    $minutes = $this->policymakerService->getMinutesOfDiscussion(NULL, TRUE);

    return [
      '#theme' => 'agendas_submenu',
      '#years' => array_keys($minutes),
      '#list' => $minutes,
      '#type' => 'documents'
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function getCacheTags(): array {
    return [
      'media_list:minutes_of_the_discussion',
      'node_list:meeting',
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function getCacheContexts(): array {
    return ['url.path', 'url.query_args'];
  }

}
