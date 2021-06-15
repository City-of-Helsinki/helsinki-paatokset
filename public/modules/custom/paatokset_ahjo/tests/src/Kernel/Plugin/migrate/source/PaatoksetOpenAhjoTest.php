<?php

declare(strict_types = 1);

namespace Drupal\Tests\paatokset_ahjo\Kernel\Plugin\migrate\source;

use Drupal\paatokset_ahjo\Plugin\migrate\source\PaatoksetOpenAhjo;
use Drupal\migrate\Plugin\MigrateSourcePluginManager;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drupal\Tests\helfi_api_base\Kernel\ApiKernelTestBase;
use GuzzleHttp\Psr7\Response;

/**
 * Tests the OpenAhjo source plugin.
 *
 * @coversDefaultClass \Drupal\paatokset_ahjo\Plugin\migrate\source\PaatoksetOpenAhjo
 * @group paatokset_ahjo
 */
class PaatoksetOpenAhjoTest extends ApiKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'migrate',
    'system',
    'paatokset_ahjo',
  ];

  /**
   * The migration plugin manager.
   *
   * @var null|\Drupal\migrate\Plugin\MigrationPluginManagerInterface
   */
  protected ?MigrationPluginManagerInterface $migrationPluginManager;

  /**
   * The source plugin manager.
   *
   * @var null|\Drupal\migrate\Plugin\MigrateSourcePluginManager
   */
  protected ?MigrateSourcePluginManager $sourcePluginManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['migrate', 'system']);

    $this->sourcePluginManager = $this->container->get('plugin.manager.migrate.source');
    $this->migrationPluginManager = $this->container->get('plugin.manager.migration');
  }

  /**
   * Make sure "url" is required setting.
   */
  public function testMissingUrl() : void {
    $this->expectException(\InvalidArgumentException::class);
    $migration = $this->createMock(MigrationInterface::class);

    PaatoksetOpenAhjo::create($this->container, [], 'json_api', [], $migration);
  }

  /**
   * Creates response json.
   *
   * @param int|null $offset
   *   The offset.
   *
   * @return string
   *   The JSON.
   */
  private function createResponseJson(?int $offset) : string {
    $url = $offset ? sprintf('/v1/issue/?order_by=-last_modified_time&limit=20&offset=%s', $offset) : NULL;

    return \GuzzleHttp\json_encode([
      'meta' => [
        'limit' => 20,
        'next' => $url,
        'offset' => $offset,
        'total_count' => 60,
      ],
      'objects' => array_map(function () {
        static $i = 0;
        return ['subject' => 'Subject ' . ++$i, 'id' => $i];
      }, array_fill(0, 20, NULL)),
    ]);
  }

  /**
   * Tests 'paatokset_open_ahjo' source plugin.
   */
  public function testSourcePlugin() : void {
    $this->container->set('http_client', $this->createMockHttpClient([
      new Response(200, [], $this->createResponseJson(0)),
      new Response(200, [], $this->createResponseJson(20)),
      new Response(200, [], $this->createResponseJson(NULL)),
    ]));

    $migration = $this->migrationPluginManager->createStubMigration([
      'source' => [
        'plugin' => 'paatokset_open_ahjo',
        'track_changes' => 'true',
        'url' => 'http://localhost/v1/issue/?order_by=-last_modified_time',
      ],
      'process' => [],
      'destination' => [
        'plugin' => 'null',
      ],
    ]);

    $configuration = [
      'url' => 'http://localhost/v1/issue/?order_by=-last_modified_time',
    ];
    /** @var \Drupal\paatokset_ahjo\Plugin\migrate\source\PaatoksetOpenAhjo $source */
    $source = $this->sourcePluginManager->createInstance('paatokset_open_ahjo', $configuration, $migration);
    $this->assertCount(60, $source);
    $this->assertEquals((string) $source, 'OpenAhjo');
    $this->assertArrayHasKey('id', $source->getIds());

    $source->rewind();

    $this->assertEquals($source->current()->getSource()['subject'], 'Subject 1');
    $this->assertEquals($source->current()->getSource()['id'], '1');

    $source->next();
    $this->assertEquals($source->current()->getSource()['subject'], 'Subject 2');
    $this->assertEquals($source->current()->getSource()['id'], '2');
  }

}
