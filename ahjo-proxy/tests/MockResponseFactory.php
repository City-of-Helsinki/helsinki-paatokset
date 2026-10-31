<?php

declare(strict_types=1);

namespace App\Tests;

use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Mock response factory for the test http client.
 */
final class MockResponseFactory {

  /**
   * Captured requests.
   *
   * @var array<array{method: string, url: string, options: array<string, mixed>}>
   */
  public static array $requests = [];

  /**
   * Fixed response bodies by URL.
   *
   * @var array<string, string>
   */
  public static array $fixedResponses = [];

  /**
   * Response factory.
   *
   * @var null|\Closure(string, string, array<string, mixed>): ResponseInterface
   */
  private static ?\Closure $factory = NULL;

  /**
   * Sets the response, or a callback that creates the response.
   *
   * @param \Symfony\Contracts\HttpClient\ResponseInterface|\Closure(string, string, array<string, mixed>): ResponseInterface $response
   *   The response.
   */
  public static function setResponse(ResponseInterface|\Closure $response): void {
    self::$factory = $response instanceof \Closure ? $response : static fn () => $response;
  }

  /**
   * Resets the state.
   */
  public static function reset(): void {
    self::$requests = [];
    self::$fixedResponses = [];
    self::$factory = NULL;
  }

  /**
   * Creates the response.
   *
   * @param string $method
   *   Request method.
   * @param string $url
   *   Request URL.
   * @param array<string, mixed> $options
   *   Request options.
   */
  public function __invoke(string $method, string $url, array $options = []): ResponseInterface {
    if (isset(self::$fixedResponses[$url])) {
      return new MockResponse(self::$fixedResponses[$url], [
        'response_headers' => ['Content-Type' => 'application/json'],
      ]);
    }

    self::$requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

    if (!self::$factory) {
      return new MockResponse('', ['error' => 'Unexpected request: ' . $url]);
    }

    return (self::$factory)($method, $url, $options);
  }

}
