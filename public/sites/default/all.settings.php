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

// Search operator guide node id.
$config['paatokset_search.settings']['operator_guide_node_id'] = getenv('OPERATOR_GUIDE_NODE_ID');

$config['paatokset_ahjo_api.settings']['ahjo_endpoint'] = 'https://ahjo.hel.fi:9802/ahjorest/v1/';

// AD role mapping.
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

$config['helfi_api_base.api_accounts']['vault'][] = [
  'id' => 'allu',
  'plugin' => 'json',
  'data' => json_encode([
    'username' => getenv('ALLU_USERNAME'),
    'password' => getenv('ALLU_PASSWORD'),
  ]),
];

$config['paatokset_ahjo_api.settings']['proxy_api_key'] = getenv('LOCAL_PROXY_API_KEY');

if (getenv('ALLU_BASE_URL')) {
  $config['paatokset_allu.settings']['base_url'] = getenv('ALLU_BASE_URL');
}

$additionalEnvVars = [
  // @todo https://helsinkisolutionoffice.atlassian.net/browse/UHF-9640.
  // 'ALLU_USERNAME',
  // 'ALLU_PASSWORD',
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
  'ELASTICSEARCH_URL',
  'ELASTIC_USER',
  'ELASTIC_PASSWORD',
  'SENTRY_DSN_REACT',
  'OPERATOR_GUIDE_NODE_ID',
];

foreach ($additionalEnvVars as $var) {
  $preflight_checks['environmentVariables'][] = $var;
}
