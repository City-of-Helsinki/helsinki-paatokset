<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\AutowiredInstanceTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\paatokset_ahjo_api\Service\PolicymakerService;
use Drupal\views\Entity\View;
use Drupal\views\ViewExecutableFactory;

/**
 * Provides policymaker documents block.
 */
#[Block(
  id: 'policymaker_documents',
  admin_label: new TranslatableMarkup('Policymaker documents'),
  category: new TranslatableMarkup('Paatokset custom blocks'),
)]
final class PolicymakerDocumentsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  use AutowiredInstanceTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'view' => NULL,
      'view_display' => NULL,
    ];
  }

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly PolicymakerService $policymakerService,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ViewExecutableFactory $viewExecutable,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritDoc}
   */
  public function build(): array {
    $view = $this->entityTypeManager
      ->getStorage('view')
      ->load($this->configuration['view']);

    assert($view instanceof View);
    $executable = $this->viewExecutable->get($view);

    // Get policymaker from magic value and
    // pass it to view contextual filters.
    $policymaker = $this->policymakerService->getPolicyMaker();

    if (!$policymaker) {
      $build = [];

      $cache = new CacheableMetadata();
      $cache->setCacheMaxAge(60);
      $cache->applyTo($build);
      return $build;
    }

    $display = $this->configuration['view_display'] ?? 'default';
    return $executable->buildRenderable($display, [$policymaker->getPolicymakerId()]);
  }

}
