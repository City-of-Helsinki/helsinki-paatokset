<?php

declare(strict_types=1);

namespace Drupal\paatokset\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_api_base\Language\DefaultLanguageResolver;
use Drupal\paatokset\Lupapiste\ItemsStorage;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an RSS feed block.
 */
#[Block(
  id: "rss_feed",
  admin_label: new TranslatableMarkup("RSS Feed"),
)]
final class RssFeedBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The rss storage.
   *
   * @var \Drupal\paatokset\Lupapiste\ItemsStorage
   */
  private ItemsStorage $rssStorage;

  /**
   * The language resolver.
   *
   * @var \Drupal\helfi_api_base\Language\DefaultLanguageResolver
   */
  private DefaultLanguageResolver $languageResolver;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ) : self {
    $instance = new self($configuration, $plugin_id, $plugin_definition);
    $instance->rssStorage = $container->get(ItemsStorage::class);
    $instance->languageResolver = $container->get(DefaultLanguageResolver::class);
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $build = [
      '#cache' => [
        'contexts' => ['languages:language_content'],
        'tags' => ItemsStorage::CACHE_TAGS,
      ],
    ];

    $langcode = $this->languageResolver->getCurrentOrFallbackLanguage(LanguageInterface::TYPE_CONTENT);

    foreach ($this->rssStorage->load($langcode) as $item) {
      $build[] = [
        '#theme' => 'lupapiste_rss_item',
        '#item' => $item,
      ];
    }
    return $build;
  }

}
