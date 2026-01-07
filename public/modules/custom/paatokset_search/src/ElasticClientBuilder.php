<?php

declare(strict_types=1);

namespace Drupal\paatokset_search;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\elasticsearch_connector\Plugin\search_api\backend\ElasticSearchBackend;
use Drupal\search_api\Entity\Server;
use Elastic\Elasticsearch\Client;

/**
 * Builds elasticsearch client.
 */
final readonly class ElasticClientBuilder {

  public function __construct(
    private EntityTYpeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * Creates a new client instance.
   *
   * @return \Elastic\Elasticsearch\Client
   *   The client.
   */
  public function create() : Client {
    $server = $this->entityTypeManager
      ->getStorage('search_api_server')
      ->load('default');
    assert($server instanceof Server);

    $backend = $server->getBackend();
    assert($backend instanceof ElasticSearchBackend);

    return $backend->getClient();
  }

}
