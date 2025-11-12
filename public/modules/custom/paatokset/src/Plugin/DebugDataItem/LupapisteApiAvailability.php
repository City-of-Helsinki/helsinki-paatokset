<?php

declare(strict_types=1);

namespace Drupal\paatokset\Plugin\DebugDataItem;

use Drupal\Core\DependencyInjection\AutowiredInstanceTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_api_base\Attribute\DebugDataItem;
use Drupal\helfi_api_base\Debug\SupportsValidityChecksInterface;
use Drupal\helfi_api_base\DebugDataItemPluginBase;
use Drupal\paatokset\Lupapiste\ItemsImporter;

/**
 * Debug data plugin for Lupapiste RSS service.
 *
 * This is used to ensure Drupal can connect to the Lupapiste service.
 */
#[DebugDataItem(
  id: 'lupapiste',
  title: new TranslatableMarkup('Lupapiste'),
)]
final class LupapisteApiAvailability extends DebugDataItemPluginBase implements ContainerFactoryPluginInterface, SupportsValidityChecksInterface {

  use AutowiredInstanceTrait;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly ItemsImporter $importer,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function check(): bool {
    $data = $this->importer->fetch('fi');

    return !empty($data['items']);
  }

}
