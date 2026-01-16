<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\SearchApi;

use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Processor\ProcessorInterface;
use Drupal\search_api\ServerInterface;
use Drupal\Tests\paatokset_ahjo_api\Kernel\AhjoEntityKernelTestBase;

/**
 * Base class for search api related kernel tests.
 *
 * @see \Drupal\Tests\search_api\Kernel\Processor\ProcessorTestBase
 */
abstract class AhjoSearchApiKernelTestBase extends AhjoEntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'search_api',
    'search_api_db',
  ];

  /**
   * The processor used for this test.
   */
  protected ProcessorInterface $processor;

  /**
   * The search index used for this test.
   */
  protected IndexInterface $index;

  /**
   * The search server used for this test.
   */
  protected ServerInterface $server;

  /**
   * Performs setup tasks before each individual test method is run.
   *
   * Installs commonly used schemas and sets up a search server and an index,
   * with the specified processor enabled.
   *
   * @param string|null $processor
   *   (optional) The plugin ID of the processor that should be set up for
   *   testing.
   */
  public function setUp(?string $processor = NULL): void {
    parent::setUp();

    $this->installSchema('search_api', ['search_api_item']);
    $this->installEntitySchema('search_api_task');
    $this->installConfig('search_api');

    $this->server = Server::create([
      'id' => 'server',
      'name' => 'Server & Name',
      'status' => TRUE,
      'backend' => 'search_api_db',
      'backend_config' => [
        'min_chars' => 3,
        'database' => 'default:default',
      ],
    ]);
    $this->server->save();

    $this->index = Index::create([
      'id' => 'index',
      'name' => 'Index name',
      'status' => TRUE,
      'datasource_settings' => [
        'entity:node' => [],
      ],
      'server' => 'server',
      'tracker_settings' => [
        'default' => [],
      ],
    ]);
    $this->index->setServer($this->server);

    if ($processor) {
      $this->processor = $this->container
        ->get('search_api.plugin_helper')
        ->createProcessorPlugin($this->index, $processor);
      $this->index->addProcessor($this->processor);
    }
    $this->index->save();
  }

}
