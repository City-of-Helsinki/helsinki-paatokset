<?php

declare(strict_types=1);

namespace Drupal\paatokset\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Drupal\paatokset\Entity\Article;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides All Articles Block.
 */
#[Block(
  id: 'all_articles',
  admin_label: new TranslatableMarkup('Paatokset all articles'),
  category: new TranslatableMarkup('Paatokset custom blocks'),
)]
final class ArticlesBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a new instance.
   *
   * @param array $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   Plugin id.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    private readonly LanguageManagerInterface $languageManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get(LanguageManagerInterface::class),
      $container->get(EntityTypeManagerInterface::class),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function build(): array {
    $nodeStorage = $this->entityTypeManager->getStorage('node');
    $viewBuilder = $this->entityTypeManager->getViewBuilder('node');

    $nids = $nodeStorage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'article')
      ->condition('langcode', $this->languageManager->getCurrentLanguage()->getId())
      ->condition('status', NodeInterface::PUBLISHED)
      ->sort('sticky', 'DESC')
      // Field defined by publication_date module.
      ->sort('published_at', 'DESC')
      ->execute();

    // @fixme: loading too many nodes with loadMultiple may lead to OOM.
    $nodes = $nodeStorage->loadMultiple($nids);

    $byYear = [];
    foreach ($nodes as $node) {
      assert($node instanceof Article);

      $byYear[$node->getPublishedYear()][] = $viewBuilder->view($node, 'teaser');
    }

    if (empty($byYear)) {
      return [];
    }

    return [
      '#theme' => 'all_articles',
      '#articles_by_year' => $byYear,
      '#attributes' => [
        'class' => ['all-articles'],
      ],
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function getCacheTags(): array {
    return ['node_list:article'];
  }

  /**
   * {@inheritDoc}
   */
  public function getCacheContexts(): array {
    return ['languages:language_content'];
  }

}
