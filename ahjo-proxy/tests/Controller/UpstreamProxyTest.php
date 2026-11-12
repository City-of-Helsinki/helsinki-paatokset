<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\MockResponseFactory;
use App\Tests\StorageTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Tests the proxy controller with an upstream proxy.
 */
final class UpstreamProxyTest extends WebTestCase {

  use StorageTestTrait {
    setUp as storageSetUp;
    tearDown as storageTearDown;
  }

  private const array ENV = [
    'AHJO_UPSTREAM_PROXY_URL' => 'https://upstream.test/',
    'AHJO_UPSTREAM_PROXY_API_KEY' => 'upstream-api-key',
  ];

  /**
   * The browser.
   */
  private KernelBrowser $client;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->storageSetUp();

    // Env vars are resolved at runtime, so the new kernel picks these up.
    foreach (self::ENV as $name => $value) {
      $_ENV[$name] = $_SERVER[$name] = $value;
    }

    $this->client = self::createClient();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    foreach (array_keys(self::ENV) as $name) {
      $_ENV[$name] = $_SERVER[$name] = '';
    }

    $this->storageTearDown();
  }

  /**
   * Makes an authenticated request.
   */
  private function request(string $path): void {
    $this->client->request('GET', $path, server: ['HTTP_API_KEY' => 'test-api-key']);
  }

  /**
   * Tests that requests are forwarded to the upstream proxy.
   */
  public function testProxyRequest(): void {
    // The Ahjo auth token is not needed.
    MockResponseFactory::setResponse(new MockResponse('{"cases": []}', [
      'http_code' => 200,
      'response_headers' => ['Content-Type' => 'application/json; charset=utf-8'],
    ]));

    $this->request('/cases?apireqlang=fi&handledsince=2025-01-01T00:00:00Z&size=1000');

    $this->assertResponseIsSuccessful();
    $this->assertResponseHeaderSame('Content-Type', 'application/json; charset=utf-8');
    $this->assertSame('{"cases": []}', $this->client->getResponse()->getContent());

    $this->assertCount(1, MockResponseFactory::$requests);
    ['url' => $url, 'options' => $options] = MockResponseFactory::$requests[0];
    $this->assertSame('https://upstream.test/cases?apireqlang=fi&handledsince=2025-01-01T00:00:00Z&size=1000', $url);
    $this->assertIsArray($options['headers']);
    $this->assertContains('api-key: upstream-api-key', $options['headers']);
    foreach ($options['headers'] as $header) {
      $this->assertIsString($header);
      $this->assertStringStartsNotWith('authorization:', strtolower($header));
    }
  }

  /**
   * Tests that the local api key is still required.
   */
  public function testApiKey(): void {
    MockResponseFactory::setResponse(new MockResponse('{}'));

    $this->client->request('GET', '/cases');
    $this->assertResponseStatusCodeSame(401);

    $this->client->request('GET', '/cases', server: ['HTTP_API_KEY' => 'upstream-api-key']);
    $this->assertResponseStatusCodeSame(401);

    $this->assertEmpty(MockResponseFactory::$requests);
  }

  /**
   * Tests that upstream errors are passed through.
   */
  public function testUpstreamError(): void {
    MockResponseFactory::setResponse(new MockResponse('{"error": "Ahjo auth token is not available."}', [
      'http_code' => 503,
      'response_headers' => ['Content-Type' => 'application/json'],
    ]));

    $this->request('/cases');
    $this->assertResponseStatusCodeSame(503);
    $this->assertSame('{"error": "Ahjo auth token is not available."}', $this->client->getResponse()->getContent());

    MockResponseFactory::setResponse(new MockResponse('', ['error' => 'Connection refused']));
    $this->request('/cases');
    $this->assertResponseStatusCodeSame(502);
  }

}
