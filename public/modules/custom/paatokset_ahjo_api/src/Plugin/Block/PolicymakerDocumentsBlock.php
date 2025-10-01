<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\paatokset_policymakers\Service\PolicymakerService;
use Drupal\views\Entity\View;
use Drupal\views\ViewExecutableFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides policymaker documents block.
 */
#[Block(
  id: 'policymaker_documents',
  admin_label: new TranslatableMarkup('Policymaker documents'),
  category: new TranslatableMarkup('Paatokset custom blocks'),
)]
final class PolicymakerDocumentsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The policymaker service.
   */
  private PolicymakerService $policymakerService;

  /**
   * The entity type manager.
   */
  private EntityTypeManagerInterface $entityTypeManager;

  /**
   * The view executable factory.
   */
  private ViewExecutableFactory $viewExecutable;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'view' => NULL,
      'view_display' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $plugin = new self($configuration, $plugin_id, $plugin_definition);
    $plugin->policymakerService = $container->get('paatokset_policymakers');
    $plugin->entityTypeManager = $container->get(EntityTypeManagerInterface::class);
    $plugin->viewExecutable = $container->get(ViewExecutableFactory::class);
    return $plugin;
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
    assert($policymaker);

    $display = $this->configuration['view_display'] ?? 'default';
    return $executable->buildRenderable($display, [$policymaker->getPolicymakerId()]);
  }

}
