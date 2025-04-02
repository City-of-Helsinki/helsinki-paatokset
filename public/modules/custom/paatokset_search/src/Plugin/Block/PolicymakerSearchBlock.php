<?php

declare(strict_types=1);

namespace Drupal\paatokset_search\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
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
    private ConfigFactoryInterface $configFactory,
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
      $container->get(ConfigFactoryInterface::class),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function build(): array {
    $proxyConfig = $this->configFactory->get('elastic_proxy.settings');
    $searchConfig = $this->configFactory->get('paatokset_search.settings');
    $proxyUrl = $proxyConfig->get('elastic_proxy_url') ?: '';

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

    if ($sentryDsnReact = $searchConfig->get('sentry_dsn_react')) {
      $build['#attached']['drupalSettings']['paatokset_react_search']['sentry_dsn_react'] = $sentryDsnReact;
    }

    return $build;
  }

}
