<?php

declare(strict_types=1);

namespace Drupal\paatokset_search\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
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
  category: new TranslatableMarkup('Paatokset custom blocks'),
)]
final class PolicymakerSearchBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The search manager.
   *
   * @var \Drupal\paatokset_search\SearchManager
   */
  private SearchManager $searchManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private ConfigFactoryInterface $configFactory;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->searchManager = $container->get(SearchManager::class);
    $instance->configFactory = $container->get('config.factory');
    return $instance;
  }

  /**
   * {@inheritDoc}
   */
  public function build(): array {
    $build = $this->searchManager->build('policymakers');

    return array_merge($build, [
      '#theme' => 'policymaker_search_block',
      '#lead_in' => _paatokset_ahjo_api_render_default_text(
        $this->configFactory
          ->get('paatokset_ahjo_api.default_texts')
          ->get('policymakers_search_description') ?? []
      ),
    ]);
  }

}
