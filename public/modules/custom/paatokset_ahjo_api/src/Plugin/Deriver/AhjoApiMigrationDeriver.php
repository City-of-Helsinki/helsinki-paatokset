<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\Deriver;

use Drupal\Component\Plugin\Derivative\DeriverBase;

/**
 * Deriver for Ahjo API migrations.
 */
class AhjoApiMigrationDeriver extends DeriverBase {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    $derivatives = [
      'all',
      'latest',
      'single',
    ];

    if ($base_plugin_definition['id'] === 'ahjo_meetings') {
      $derivatives[] = 'cancelled';
    }

    if ($base_plugin_definition['id'] === 'ahjo_trustees') {
      $derivatives = [
        'all',
        'all_sv',
        'single',
        'single_sv',
      ];
    }

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

    // Set values for translation migrations.
    if (str_contains($key, '_sv')) {
      $base_plugin_definition['destination']['translations'] = TRUE;
      $base_plugin_definition['process']['langcode']['default_value'] = 'sv';
    }

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
    if (getenv('AHJO_PROXY_BASE_URL')) {
      $base_url = getenv('AHJO_PROXY_BASE_URL');
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
        'cancelled' => '/ahjo-proxy/aggregated/meetings_cancelled',
      ],
      'ahjo_cases' => [
        'all' => '/ahjo-proxy/aggregated/cases_all',
        'latest' => '/ahjo-proxy/aggregated/cases_latest',
        'single' => '/ahjo-proxy/cases/single',
      ],
      'ahjo_decisions' => [
        'all' => '/ahjo-proxy/aggregated/decisions_all',
        'latest' => '/ahjo-proxy/aggregated/decisions_latest',
        'single' => '/ahjo-proxy/decisions/single',
      ],
      'ahjo_trustees' => [
        'all' => '/ahjo-proxy/aggregated/trustees_fi',
        'all_sv' => '/ahjo-proxy/aggregated/trustees_sv',
        'single' => '/ahjo-proxy/trustees/single',
        'single_sv' => '/ahjo-proxy/trustees/single',
      ],
    ];

    if (isset($source_urls[$base_plugin_id][$key])) {
      return $base_url . $source_urls[$base_plugin_id][$key];
    }

    return NULL;
  }

}
