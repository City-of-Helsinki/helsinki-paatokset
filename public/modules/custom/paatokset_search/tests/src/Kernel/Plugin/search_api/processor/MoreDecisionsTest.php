<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_search\Kernel\Plugin\search_api\processor;

use Drupal\KernelTests\KernelTestBase;
use Drupal\paatokset_search\Plugin\search_api\processor\MoreDecisions;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Processor\ProcessorInterface;
use Drupal\search_api\Processor\ProcessorPluginManager;

/**
 * Tests the More Decisions processor.
 *
 * @coversDefaultClass \Drupal\paatokset_search\Plugin\search_api\processor\MoreDecisions
 */
class MoreDecisionsTest extends KernelTestBase {
  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'search_api',
    'search_api_db',
    'paatokset_search',
    'paatokset_ahjo_api',
    'helfi_api_base',
  ];

  /**
   * The processor plugin manager.
   *
   * @var \Drupal\search_api\Plugin\search_api\processor\ProcessorPluginManager
   */
  protected ProcessorPluginManager $processorPluginManager;

  /**
   * The search index.
   *
   * @var \Drupal\search_api\IndexInterface
   */
  protected IndexInterface $index;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->processorPluginManager = $this->container->get('plugin.manager.search_api.processor');

    // Create a search index.
    $this->index = $this->createIndex('test_index', 'test_index', 'entity:node');
  }

  protected function createIndex(string $id, string $name, string $datasource_id): IndexInterface {
    $index = $this->container->get('entity_type.manager')
      ->getStorage('search_api_index')
      ->create([
        'id' => $id,
        'name' => $name,
        'datasources' => [$datasource_id => []],
        'server' => 'default',
      ]);

    return $index;
  }

  /**
   * Tests the More Decisions processor creation.
   *
   * @covers ::__construct
   * @covers ::create
   */
  public function testCreation(): void {
    $plugin = $this->processorPluginManager->createInstance('more_decisions', [], $this->index);
    $this->assertInstanceOf(MoreDecisions::class, $plugin);
    $this->assertInstanceOf(ProcessorInterface::class, $plugin);
  }

  /**
   * Tests the property definitions.
   * 
   * @covers ::getPropertyDefinitions
   */
  public function testGetPropertyDefinitions(): void {
    $plugin = $this->processorPluginManager->createInstance('more_decisions', [], $this->index);
    $dataSource = $this->prophesize(\Drupal\search_api\Datasource\DatasourceInterface::class);
    $properties = $plugin->getPropertyDefinitions(datasource: $dataSource->reveal());

    $this->assertArrayHasKey('more_decisions', $properties);
    $this->assertNotEmpty($properties['more_decisions']->getLabel());
    $this->assertNotEmpty($properties['more_decisions']->getDescription());
  }
}
