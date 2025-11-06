<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\DebugDataItem;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_api_base\Attribute\DebugDataItem;
use Drupal\helfi_api_base\Debug\SupportsValidityChecksInterface;
use Drupal\helfi_api_base\DebugDataItemPluginBase;
use Drupal\paatokset_ahjo_api\AhjoOpenId\AhjoOpenId;

/**
 * Debug data plugin for Ahjo token.
 *
 * This is used to ensure the Ahjo token has not expired.
 */
#[DebugDataItem(
  id: 'ahjo_token',
  title: new TranslatableMarkup('Ahjo token'),
)]
final class AhjoToken extends DebugDataItemPluginBase implements ContainerFactoryPluginInterface, SupportsValidityChecksInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly AhjoOpenId $ahjoOpenId,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function check(): bool {
    return $this->ahjoOpenId->checkAuthToken();
  }

}
