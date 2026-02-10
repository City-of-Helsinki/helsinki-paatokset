<?php

declare(strict_types=1);

namespace Drupal\paatokset_search\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\paatokset_search\SearchManager;
/**
 * Provides Block for policymaker search.
 */
#[Block(
  id: 'policymaker_search_block',
  admin_label: new TranslatableMarkup('Paatokset policymaker search'),
  category: new TranslatableMarkup('Paatokset custom blocks'),
)]
final class PolicymakerSearchBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly SearchManager $searchManager,
    private readonly ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
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
