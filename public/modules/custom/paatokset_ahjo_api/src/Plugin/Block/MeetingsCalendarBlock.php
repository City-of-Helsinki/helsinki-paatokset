<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides Calendar Block.
 */
#[Block(
  id: 'meetings_calendar',
  admin_label: new TranslatableMarkup('Paatokset meetings calendar'),
  category: new TranslatableMarkup('Paatokset custom blocks'),
)]
final class MeetingsCalendarBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The config factory.
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string, mixed> $configuration
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    $instance = new self($configuration, $plugin_id, $plugin_definition);
    $instance->configFactory = $container->get(ConfigFactoryInterface::class);
    return $instance;
  }

  /**
   * {@inheritDoc}
   */
  public function build(): array {
    $config = $this->configFactory->get('paatokset_ahjo_api.default_texts');
    $description = $config->get('meeting_calendar_description');

    $build = [
      '#theme' => 'meetings_calendar',
      '#description' => $description !== NULL ? _paatokset_ahjo_api_render_default_text($description) : NULL,
      '#attached' => [
        'library' => ['hdbt_subtheme/meetings-calendar'],
      ],
    ];

    CacheableMetadata::createFromObject($config)->applyTo($build);

    return $build;
  }

}
