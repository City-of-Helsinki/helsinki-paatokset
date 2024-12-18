<?php

namespace Drupal\paatokset_search\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides Block for policymaker search.
 *
 * @Block(
 *    id = "policymaker_search_block",
 *    admin_label = @Translation("Paatokset policymaker search"),
 *    category = @Translation("Paatokset custom blocks")
 * )
 */
class PolicymakerSearchBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritDoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private ImmutableConfig $proxyConfig,
    private ImmutableConfig $searchConfig,
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
      $container->get('config.factory')->get('elastic_proxy.settings'),
      $container->get('config.factory')->get('paatokset_search.settings')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function build(): array {
    $proxyUrl = $this->proxyConfig->get('elastic_proxy_url') ?: '';

    $build = [
      '#markup' => '<div class="paatokset-search-wrapper"><div id="paatokset_search" data-type="policymakers" data-url="' . $proxyUrl . '"></div></div>',
      '#attributes' => [
        'class' => ['policymaker-search'],
      ],
      '#attached' => [
        'library' => [
          'paatokset_search/paatokset-search',
        ],
      ],
    ];

    if ($sentryDsnReact = $this->searchConfig->get('sentry_dsn_react')) {
      $build['#attached']['drupalSettings']['paatokset_react_search']['sentry_dsn_react'] = $sentryDsnReact;
    }

    return $build;
  }

}
