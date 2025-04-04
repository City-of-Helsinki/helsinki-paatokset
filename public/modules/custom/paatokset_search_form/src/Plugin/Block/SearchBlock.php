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
final class SearchBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The search manager.
   *
   * @var \Drupal\paatokset_search\SearchManager
   */
  private SearchManager $searchManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->searchManager = $container->get(SearchManager::class);

    return $instance;
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
