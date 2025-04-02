<?php

declare(strict_types=1);

namespace Drupal\paatokset_search\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\paatokset_search\SearchManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides Block for policymaker search.
 */
#[Block(
  id: 'policymaker_search_block',
  admin_label: new TranslatableMarkup('Paatokset policymaker search'),
  category: new TranslatableMarkup('Paatokset custom blocks')
)]
class PolicymakerSearchBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritDoc}
   */
  final public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private SearchManager $searchManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get(SearchManager::class),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function build(): array {
    $build = [
      '#attributes' => [
        'class' => ['policymaker-search'],
      ],
      'search_wrapper' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['paatokset-search-wrapper'],
        ],
        'search' => $this->searchManager->build('policymakers'),
      ],
    ];

    return $build;
  }

}
