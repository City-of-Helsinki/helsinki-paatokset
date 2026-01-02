<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_search\Kernel\Controller;

use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\paatokset_search\Controller\SearchController;
use Drupal\paatokset_search\SearchManager;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Elastic\Elasticsearch\ClientBuilder;
use Elastic\Elasticsearch\Response\Elasticsearch;
use GuzzleHttp\Psr7\Response;

/**
 * Tests search controller.
 */
class SearchControllerTest extends KernelTestBase {

  use ApiTestTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'paatokset_search',
    'system',
    'user',
  ];

  /**
   * Tests block render.
   */
  public function testDecisions(): void {
    $client = ClientBuilder::create()
      ->setHttpClient($this->createMockHttpClient([]))
      ->build();

    $controller = new SearchController($this->container->get(SearchManager::class), $client);
    $build = $controller->decisions();

    $this->assertNotEmpty($build);
  }

  /**
   * Tests autocomplete route.
   */
  public function testAutocomplete(): void {
    $history = [];
    $mock = $this->createMockHistoryMiddlewareHttpClient($history, [
      new Response(
        200,
        [
          Elasticsearch::HEADER_CHECK => Elasticsearch::PRODUCT_NAME,
          'Content-Type' => 'application/json',
        ],
        file_get_contents(dirname(__DIR__, 3) . '/fixtures/paatokset_decisions_autocomplete.json'),
      ),
    ]);

    $client = ClientBuilder::create()
      ->setHttpClient($mock)
      ->build();

    $this->container->set('paatokest_search.elastic_client', $client);

    $this->installEntitySchema('user');
    $this->setUpCurrentUser(permissions: ['access content']);

    $request = $this->getMockedRequest(Url::fromRoute('paatokset_search.autocomplete')->toString(), parameters: ['q' => 'test']);
    $response = $this->processRequest($request);

    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('application/json', $response->headers->get('Content-Type'));
    $this->assertTrue(json_validate($response->getContent()));
  }

}
