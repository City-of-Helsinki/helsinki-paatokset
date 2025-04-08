<?php

declare(strict_types=1);

namespace Drupal\paatokset\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_api_base\Language\DefaultLanguageResolver;
use Drupal\paatokset\Lupapiste\ItemsLazyBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an RSS feed block.
 */
#[Block(
  id: "lupapiste_rss_feed",
  admin_label: new TranslatableMarkup("RSS Feed"),
)]
final class LupapisteRssBlock extends BlockBase implements ContainerFactoryPluginInterface {

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
    $instance->languageResolver = $container->get(DefaultLanguageResolver::class);
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $langcode = $this->languageResolver->getCurrentOrFallbackLanguage(LanguageInterface::TYPE_CONTENT);

    return [
      '#lazy_builder' => [ItemsLazyBuilder::class . ':build', [$langcode]],
      '#create_placeholder' => TRUE,
      '#lazy_builder_preview' => ['#markup' => ''],
    ];
  }

}
