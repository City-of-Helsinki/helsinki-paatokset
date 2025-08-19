<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Drupal\paatokset_policymakers\Service\PolicymakerService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides Agendas Submenu Block.
 */
#[Block(
  id: 'agendas_submenu',
  admin_label: new TranslatableMarkup('Agendas Submenu'),
  category: new TranslatableMarkup('Paatokset custom blocks'),
)]
final class AgendasSubmenuBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The policymaker service.
   */
  private PolicymakerService $policymakerService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $plugin = new self($configuration, $plugin_id, $plugin_definition);
    $plugin->policymakerService = $container->get('paatokset_policymakers');
    return $plugin;
  }

  /**
   * {@inheritDoc}
   */
  public function build(): array {
    $list = $this->policymakerService->getAgendasListFromElasticSearch(NULL, TRUE);
    $years = array_keys($list);

    return [
      '#theme' => 'agendas_submenu',
      '#title' => 'Viranhaltijapäätökset',
      '#years' => $years,
      '#list' => $list,
      '#type' => 'decisions',
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function getCacheTags(): array {
    $policymaker = $this->policymakerService->getPolicyMaker();
    if ($policymaker instanceof NodeInterface && $policymaker->hasField('field_policymaker_id')) {
      $policymaker_id = $policymaker->get('field_policymaker_id')->value;
      return ['decision_pm:' . $policymaker_id];
    }
    return [];
  }

  /**
   * {@inheritDoc}
   */
  public function getCacheContexts(): array {
    return ['url.path', 'url.query_args'];
  }

}
