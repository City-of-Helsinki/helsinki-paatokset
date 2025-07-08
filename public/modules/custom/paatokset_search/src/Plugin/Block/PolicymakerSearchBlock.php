<?php

declare(strict_types=1);

namespace Drupal\paatokset_search\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\paatokset_ahjo_api\Service\DefaultTextProcessor;
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
   * The config manager.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  private ImmutableConfig $configManager;

  /**
   * The default text processor.
   *
   * @var \Drupal\paatokset_ahjo_api\Service\DefaultTextProcessor
   */
  private DefaultTextProcessor $defaultTextProcessor;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->searchManager = $container->get(SearchManager::class);
    $instance->configManager = $container->get('config.factory')->get('paatokset_ahjo_api.default_texts');
    $instance->defaultTextProcessor = $container->get('paatokset_ahjo_default_text_processor');

    return $instance;
  }

  /**
   * {@inheritDoc}
   */
  public function build(): array {
    $processor = $this->defaultTextProcessor;
    return [
      '#theme' => 'policymaker_search_block',
      '#lead_in' => $processor->process($this->configManager->get('policymakers_search_description')),
      '#search' => $this->searchManager->build('policymakers'),
    ];
  }

}
