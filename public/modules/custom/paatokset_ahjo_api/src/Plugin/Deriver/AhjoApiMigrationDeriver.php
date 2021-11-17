<?php

namespace Drupal\paatokset_ahjo_api\Plugin\Deriver;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Deriver for Ahjo API migrations.
 */
class AhjoApiMigrationDeriver extends DeriverBase implements ContainerDeriverInterface {

  /**
   * AhjoApiMigrationDeriver constructor.
   */
  public function __construct() {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static();
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $derivatives = [
      'all',
      'latest',
      'single',
    ];

    foreach ($derivatives as $key) {
      $derivative = $this->getDerivativeValues($base_plugin_definition, $key);
      $this->derivatives[$key] = $derivative;
    }

    return $this->derivatives;
  }

  /**
   * Creates a derivative definition for each available language.
   *
   * @param array $base_plugin_definition
   *   Base migration definitions.
   * @param string $key
   *   Key for derivative.
   *
   * @return array
   *   Modified plugin definition for derivative.
   */
  protected function getDerivativeValues(array $base_plugin_definition, string $key): array {
    // Single import requires programmatically set ID, so skip counting it.
    if ($key === 'single') {
      $base_plugin_definition['source']['skip_count'] = TRUE;
    }

    $source_url = $this->getSourceUrl($base_plugin_definition['id'], $key);
    $base_plugin_definition['source']['urls'] = [
      $source_url,
    ];

    return $base_plugin_definition;
  }

  /**
   * Gets source URL (and local base URL) based on plugin ID and derivative key.
   *
   * @param string $base_plugin_id
   *   Base plugin ID.
   * @param string $key
   *   Derivative key.
   *
   * @return string|null
   *   Correct source URL (if found).
   */
  protected function getSourceUrl(string $base_plugin_id, string $key): ?string {
    // Get either local proxy URL or OpenShift reverse proxy address.
    if (getenv('AHJO-PROXY-BASE-URL')) {
      $base_url = getenv('AHJO-PROXY-BASE-URL');
    }
    elseif (getenv('DRUPAL_REVERSE_PROXY_ADDRESS')) {
      $base_url = 'https://' . getenv('DRUPAL_REVERSE_PROXY_ADDRESS');
    }
    else {
      $base_url = '';
    }

    $source_urls = [
      'ahjo_meetings' => [
        'all' => '/ahjo-proxy/aggregated/meetings_all',
        'latest' => '/ahjo-proxy/aggregated/meetings_latest',
        'single' => '/ahjo-proxy/meetings/single',
      ],
      'ahjo_cases' => [
        'all' => '/ahjo-proxy/aggregated/cases_all',
        'latest' => '/ahjo-proxy/aggregated/cases_latest',
        'single' => '/ahjo-proxy/cases/single',
      ],
    ];

    if (isset($source_urls[$base_plugin_id][$key])) {
      return $base_url . $source_urls[$base_plugin_id][$key];
    }

    return NULL;
  }

}
