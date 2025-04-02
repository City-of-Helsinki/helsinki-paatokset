<?php

namespace Drupal\paatokset_search_form\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\paatokset_search\SearchManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block containing search form.
 *
 * @Block(
 *  id="search_form_block",
 *  admin_label=@Translation("Search block")
 * )
 */
class SearchBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritDoc}
   */
  public function __construct(
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
   * {@inheritdoc}
   */
  public function build() {
    $build = [
      '#attributes' => [
        'class' => ['paatokset-search--frontpage'],
      ],
      'search_wrapper' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['paatokset-search-wrapper'],
        ],
        'search' => $this->searchManager->build('frontpage'),
      ],
    ];

    return $build;
  }

}
