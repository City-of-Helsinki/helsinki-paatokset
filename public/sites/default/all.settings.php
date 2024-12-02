<?php

/**
 * @file
 * Contains site specific overrides.
 */

$settings['http_client_config']['timeout'] = 240;
ini_set('default_socket_timeout', 240);

// Elastic proxy URL.
$config['elastic_proxy.settings']['elastic_proxy_url'] = drupal_get_env(['REACT_APP_PROXY_URL', 'REACT_APP_ELASTIC_URL']);

// Sentry DSN for React.
$config['paatokset_search.settings']['sentry_dsn_react'] = getenv('SENTRY_DSN_REACT');

// AD role mapping
$config['openid_connect.client.tunnistamo']['settings']['ad_roles'] = [
  [
    'ad_role' => 'Drupal_Helfi_kaupunkitaso_paakayttajat',
    'roles' => ['admin'],
  ],
  [
    'ad_role' => 'Drupal_Helfi_Paatokset_sisallontuottajat_laaja',
    'roles' => ['editor'],
  ],
  [
    'ad_role' => 'Drupal_Helfi_Paatokset_sisallontuottajat_suppea',
    'roles' => ['content_producer'],
  ],
  [
    'ad_role' => '947058f4-697e-41bb-baf5-f69b49e5579a',
    'roles' => ['super_administrator'],
  ],
];

$additionalEnvVars = [
  'DRUPAL_VARNISH_HOST',
  'DRUPAL_VARNISH_PORT',
  'REDIS_HOST',
  'REDIS_PORT',
  'REDIS_PASSWORD',
  'TUNNISTAMO_CLIENT_ID',
  'TUNNISTAMO_CLIENT_SECRET',
  'TUNNISTAMO_ENVIRONMENT_URL',
  'SENTRY_DSN',
  'SENTRY_ENVIRONMENT',
  // Project specific variables.
  'DRUPAL_REVERSE_PROXY_ADDRESS|AHJO_PROXY_BASE_URL',
  'LOCAL_PROXY_API_KEY',
  'REACT_APP_PROXY_URL|REACT_APP_ELASTIC_URL',
  'ELASTIC_CONNECTOR_URL',
  'ELASTIC_INTERNAL_USER',
  'ELASTIC_INTERNAL_PWD',
  'SENTRY_DSN_REACT',
];

foreach ($additionalEnvVars as $var) {
  $preflight_checks['environmentVariables'][] = $var;
}
