<?php

/**
 * @file
 * Contains site specific overrides.
 */

$settings['http_client_config']['timeout'] = 240;
ini_set('default_socket_timeout', 240);

// Elasticsearch settings.
if (getenv('ELASTIC_CONNECTOR_URL')) {
  $config['elasticsearch_connector.cluster.paatokset']['url'] = getenv('ELASTIC_CONNECTOR_URL');

  if(getenv('ELASTIC_INTERNAL_USER') && getenv('ELASTIC_INTERNAL_PWD')) {
    $config['elasticsearch_connector.cluster.paatokset']['options']['use_authentication'] = '1';
    $config['elasticsearch_connector.cluster.paatokset']['options']['authentication_type'] = 'Basic';
    $config['elasticsearch_connector.cluster.paatokset']['options']['username'] = getenv('ELASTIC_INTERNAL_USER');
    $config['elasticsearch_connector.cluster.paatokset']['options']['password'] = getenv('ELASTIC_INTERNAL_PWD');
  }
}
