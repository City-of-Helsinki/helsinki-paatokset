<?php

declare(strict_types=1);

namespace Drupal\paatokset_submenus\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Drupal\paatokset_policymakers\Service\PolicymakerService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides Agendas Submenu Documents Block.
 */
#[Block(
  id: 'agendas_submenu_documents',
  admin_label: new TranslatableMarkup('Paatokset policymaker documents'),
  category: new TranslatableMarkup('Paatokset custom blocks')
)]
class DocumentsBlock extends BlockBase implements ContainerFactoryPluginInterface {

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
    $list = $this->policymakerService->getApiMinutesFromElasticSearch(NULL, TRUE);

    return [
      '#title' => $this->t('Office holder decisions'),
      '#years' => array_keys($list),
      '#list' => $list,
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function getCacheTags(): array {
    $policymaker = $this->policymakerService->getPolicyMaker();
    if ($policymaker instanceof NodeInterface && $policymaker->hasField('field_policymaker_id')) {
      $policymaker_id = $policymaker->get('field_policymaker_id')->value;
      return ['meeting_pm:' . $policymaker_id];
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
