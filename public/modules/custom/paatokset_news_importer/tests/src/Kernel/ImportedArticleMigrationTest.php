<?php

declare(strict_types = 1);

namespace Drupal\Tests\paatokset_news_importer\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\migrate\MigrateMessageInterface;
use Drupal\node\Entity\Node;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\helfi_api_base\Traits\MigrationTestTrait;
use Drupal\Tests\paatokset_news_importer\Traits\ImportedArticleTestTrait;
use GuzzleHttp\Psr7\Response;

/**
 * Tests Imported article source plugin.
 */
class ImportedArticleMigrationTest extends KernelTestBase implements MigrateMessageInterface {
  use ApiTestTrait;
  use ImportedArticleTestTrait;
  use MigrationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'migrate',
    'field',
    'text',
    'image',
    'file',
    'user',
    'node',
    'language',
    'content_translation',
    'paatokset_news_importer',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig([
      'system',
      'migrate',
      'field',
      'file',
      'image',
      'text',
      'node',
      'user',
      'language',
      'content_translation',
      'paatokset_news_importer',
    ]);

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');

    ConfigurableLanguage::createFromLangcode('fi')->save();
  }

  /**
   * Tests paatokset_news migration.
   *
   * @dataProvider importedArticleData
   */
  public function testImportedArticleMigration(int $id, array $expected) : void {
    $response = $this->createResponseXml(2);

    $this->container->set('http_client', $this->createMockHttpClient([
      new Response(200, [], $response),
    ]));

    $configuration = [
      'urls' => 'http://localhost/rssfeed',
    ];

    $this->executeMigration('paatokset_news', ['source' => $configuration]);
    $importedArticles = Node::loadMultiple();

    $this->assertCount(2, $importedArticles);
    $this->assertEquals(TRUE, $importedArticles[$id]->hasTranslation('fi'));
    $resultNode = $importedArticles[$id]->getTranslation('fi');
    $this->assertEquals($expected['title'], $resultNode->getTitle());
    $this->assertEquals($expected['created'], $resultNode->getCreatedTime());
    $this->assertEquals($expected['lead'], $resultNode->getTranslation('fi')->get('body')->summary);
    $this->assertEquals($expected['content'], $resultNode->getTranslation('fi')->get('body')->value);
  }

  /**
   * Dataprovider for the test.
   *
   * @return array
   *   Excepted mock data
   */
  public function importedArticleData() : array {
    return [
      [
        1,
        [
          'title' => 'Title for item 1.',
          'created' => '1621456588',
          'lead' => 'Description for item 1.',
          'content' => 'Content for item 1.',
        ],
      ],
      [
        2,
        [
          'title' => 'Title for item 2.',
          'created' => '1621456588',
          'lead' => 'Description for item 2.',
          'content' => 'Content for item 2.',
        ],
      ],
    ];
  }

}
