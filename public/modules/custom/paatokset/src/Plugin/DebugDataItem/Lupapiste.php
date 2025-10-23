<?php

declare(strict_types=1);

namespace Drupal\paatokset\Plugin\DebugDataItem;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_api_base\Attribute\DebugDataItem;
use Drupal\helfi_api_base\Debug\SupportsValidityChecksInterface;
use Drupal\helfi_api_base\DebugDataItemPluginBase;
use Drupal\paatokset\Lupapiste\ItemsImporter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Debug data plugin for Lupapiste RSS service.
 *
 * This is used to ensure Drupal can connect to the Lupapiste service.
 */
#[DebugDataItem(
  id: 'lupapiste',
  title: new TranslatableMarkup('Lupapiste'),
)]
final class Lupapiste extends DebugDataItemPluginBase implements ContainerFactoryPluginInterface, SupportsValidityChecksInterface {

  /**
   * The Lupapiste data importer.
   *
   * @var \Drupal\paatokset\Lupapiste\ItemsImporter
   */
  private ItemsImporter $importer;

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
    $instance->importer = $container->get(ItemsImporter::class);
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function check(): bool {
    $data = $this->importer->fetch('fi');

    return !empty($data['items']);
  }

}
