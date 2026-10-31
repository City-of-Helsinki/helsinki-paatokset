<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\MockResponseFactory;
use App\Tests\StorageTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Tests the proxy controller.
 */
final class ProxyControllerTest extends WebTestCase {

  use StorageTestTrait {
    setUp as storageSetUp;
  }

  /**
   * The browser.
   */
  private KernelBrowser $client;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->storageSetUp();
    $this->client = self::createClient();
  }

  /**
   * Makes an authenticated request.
   */
  private function request(string $method, string $path): void {
    $this->client->request($method, $path, server: ['HTTP_API_KEY' => 'test-api-key']);
  }

  /**
   * Tests that allowlisted requests are forwarded to Ahjo.
   */
  public function testProxyRequest(): void {
    self::setToken();
    MockResponseFactory::setResponse(new MockResponse('{"cases": []}', [
      'http_code' => 200,
      'response_headers' => ['Content-Type' => 'application/json; charset=utf-8'],
    ]));

    $this->request('GET', '/cases?apireqlang=fi&handledsince=2025-01-01T00:00:00Z&size=1000');

    $this->assertResponseIsSuccessful();
    $this->assertResponseHeaderSame('Content-Type', 'application/json; charset=utf-8');
    $this->assertSame('{"cases": []}', $this->client->getResponse()->getContent());

    $this->assertCount(1, MockResponseFactory::$requests);
    ['method' => $method, 'url' => $url, 'options' => $options] = MockResponseFactory::$requests[0];
    $this->assertSame('GET', $method);
    $this->assertSame('https://ahjo.test/ahjorest/v1/cases?apireqlang=fi&handledsince=2025-01-01T00:00:00Z&size=1000', $url);
    $this->assertIsArray($options['headers']);
    $this->assertContains('Authorization: Bearer access-token', $options['headers']);
  }

  /**
   * Tests endpoints with path parameters.
   */
  public function testPathParameters(): void {
    self::setToken();
    MockResponseFactory::setResponse(static fn () => new MockResponse('{}'));

    $this->request('GET', '/meetings/U02120202017/agendaitems/HEL-2025-007790');
    $this->assertResponseIsSuccessful();

    $this->request('GET', '/records/%7B12345678-ABCD%7D');
    $this->assertResponseIsSuccessful();

    $this->request('GET', '/organization/decisionmakingorganizations?orgid=02900');
    $this->assertResponseIsSuccessful();

    $this->assertSame([
      'https://ahjo.test/ahjorest/v1/meetings/U02120202017/agendaitems/HEL-2025-007790',
      'https://ahjo.test/ahjorest/v1/records/%7B12345678-ABCD%7D',
      'https://ahjo.test/ahjorest/v1/organization/decisionmakingorganizations?orgid=02900',
    ], array_column(MockResponseFactory::$requests, 'url'));
  }

  /**
   * Tests that Ahjo errors are passed through.
   */
  public function testUpstreamError(): void {
    self::setToken();
    MockResponseFactory::setResponse(new MockResponse('{"error": "not found"}', [
      'http_code' => 404,
      'response_headers' => ['Content-Type' => 'application/json'],
    ]));

    $this->request('GET', '/cases/HEL-2025-000000');
    $this->assertResponseStatusCodeSame(404);
    $this->assertSame('{"error": "not found"}', $this->client->getResponse()->getContent());

    MockResponseFactory::setResponse(new MockResponse('', ['error' => 'Connection refused']));
    $this->request('GET', '/cases');
    $this->assertResponseStatusCodeSame(502);
  }

  /**
   * Tests requests without a token.
   */
  public function testMissingToken(): void {
    MockResponseFactory::setResponse(new MockResponse('{}'));

    $this->request('GET', '/cases');
    $this->assertResponseStatusCodeSame(503);
    $this->assertEmpty(MockResponseFactory::$requests);
  }

  /**
   * Tests api key authentication.
   */
  public function testApiKey(): void {
    self::setToken();
    MockResponseFactory::setResponse(new MockResponse('{}'));

    $this->client->request('GET', '/cases');
    $this->assertResponseStatusCodeSame(401);

    $this->client->request('GET', '/cases', server: ['HTTP_API_KEY' => 'wrong']);
    $this->assertResponseStatusCodeSame(401);

    $this->assertEmpty(MockResponseFactory::$requests);

    // Health check does not require api key.
    $this->client->request('GET', '/healthz');
    $this->assertResponseIsSuccessful();
  }

  /**
   * Tests that only allowlisted endpoints are proxied.
   */
  public function testUnknownEndpoints(): void {
    self::setToken();
    MockResponseFactory::setResponse(new MockResponse('{}'));

    foreach (['/', '/foo', '/cases/..', '/cases/%2E%2E', '/cases/foo%2Fbar', '/cases/1/2', '/document/123'] as $path) {
      $this->request('GET', $path);
      $this->assertFalse($this->client->getResponse()->isSuccessful(), $path);
    }

    $this->request('POST', '/cases');
    $this->assertResponseStatusCodeSame(405);

    $this->assertEmpty(MockResponseFactory::$requests);
  }

}
