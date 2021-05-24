<?php

declare(strict_types = 1);

namespace Drupal\Tests\paatokset_news_importer\Kernel\Plugin\migrate\source;

use Drupal\KernelTests\KernelTestBase;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drupal\migrate\Plugin\MigrateSourcePluginManager;
use Drupal\paatokset_news_importer\Plugin\migrate\source\ImportedArticle;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\Tests\paatokset_news_importer\Traits\ImportedArticleTestTrait;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use GuzzleHttp\Psr7\Response;

/**
 * Tests ImportedArticle source plugin.
 *
 * @coversDefaultClass \Drupal\paatokset_news_importer\Plugin\migrate\source\ImportedArticle
 * @group paatokset_news_importer
 */
class ImportedArticleTest extends KernelTestBase {
  use ApiTestTrait;
  use ImportedArticleTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'migrate',
    'system',
    'paatokset_news_importer',
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
   * Excpect failure without "url" congifuration.
   */
  public function testMissingUrl() : void {
    $this->expectException(\InvalidArgumentException::class);
    $migration = $this->createMock(MigrationInterface::class);

    ImportedArticle::create($this->container, ['ids' => ['guid' => ['type' => 'string']]], 'imported_article', [], $migration);
  }

  /**
   * Expect failulre without "id" configuration.
   */
  public function testMissingIds() : void {
    $this->expectException(\InvalidArgumentException::class);
    $migration = $this->createMock(MigrationInterface::class);

    ImportedArticle::create($this->container, ['url' => 'http://localhost/rss'], 'imported_article', [], $migration);
  }

  /**
   * Tests imported_article source plugin.
   */
  public function testSourcePlugin() : void {
    $migration = $this->migrationPluginManager->createStubMigration([
      'source' => [
        'plugin' => 'imported_article',
        'url' => 'http://localhost/rss',
        'ids' => ['guid' => ['type' => 'string']],
      ],
      'process' => [],
      'destination' => [
        'plugin' => 'null',
      ],
    ]);

    $configuration = [
      'url' => 'http://localhost/rss',
      'ids' => ['guid' => ['type' => 'string']],
    ];

    $this->container->set('http_client', $this->createMockHttpClient([
      new Response(200, [], $this->createResponseXml(3)),
      new Response(200, [], $this->createResponseXml(3)),
    ]));

    /** @var \Drupal\paatokset_news_importer\Plugin\migrate\source\ImportedArticle */
    $source = $this->sourcePluginManager->createInstance('imported_article', $configuration, $migration);
    $this->assertEquals((string) $source, 'ImportedArticle');

    $source->rewind();
    $this->assertEquals($source->current()->getSource()['guid'], '1');

    $source->next();
    $this->assertEquals($source->current()->getSource()['guid'], '2');
  }

}
