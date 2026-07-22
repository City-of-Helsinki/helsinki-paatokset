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
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests search controller.
 */
#[RunTestsInSeparateProcesses]
#[Group('paatokset_search')]
class SearchControllerTest extends KernelTestBase {

  use ApiTestTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_api_base',
    'json_field',
    'migrate',
    'paatokset_ahjo_api',
    'paatokset_search',
    'path_alias',
    'pathauto',
    'system',
    'token',
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
   * Tests policymaker search page render.
   */
  public function testPolicymakers(): void {
    $config = $this->config('paatokset_ahjo_api.default_texts');
    $config->set('policymakers_search_description', [
      'value' => 'Policymaker search description',
      'format' => 'plain_text',
    ]);
    $config->save();

    $client = ClientBuilder::create()
      ->setHttpClient($this->createMockHttpClient([]))
      ->build();

    $controller = new SearchController($this->container->get(SearchManager::class), $client);
    $build = $controller->policymakers();

    $this->assertSame('policymakers', $build['#search_element']['#attributes']['data-type']);
    $this->assertSame('Policymaker search description', $build['#lead_in']['#text']);
    $this->assertSame('processed_text', $build['#lead_in']['#type']);
    $this->assertSame('plain_text', $build['#lead_in']['#format']);
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

    $this->container->set('paatokset_search.elastic_client', $client);

    $this->installEntitySchema('user');
    $this->installEntitySchema('path_alias');
    $this->setUpCurrentUser(permissions: ['access content']);

    $request = $this->getMockedRequest(Url::fromRoute('paatokset_search.autocomplete')->toString(), parameters: ['q' => 'test']);
    $response = $this->processRequest($request);

    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('application/json', $response->headers->get('Content-Type'));
    $this->assertTrue(json_validate($response->getContent()));
  }

}
