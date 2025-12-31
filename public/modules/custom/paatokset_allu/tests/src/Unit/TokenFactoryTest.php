<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_allu\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\State\StateInterface;
use Drupal\paatokset_allu\AlluException;
use Drupal\paatokset_allu\Client\Settings;
use Drupal\paatokset_allu\Client\TokenFactory;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Http\Message\ResponseInterface;

/**
 * Tests Allu token factory.
 */
#[Group('paatokset_allu')]
class TokenFactoryTest extends UnitTestCase {

  use ProphecyTrait;
  use ApiTestTrait;

  /**
   * Tests valid token in database.
   */
  public function testValidToken(): void {
    $timestamp = time();
    $expired = $this->createMockToken([
      'exp' => $timestamp - 100,
    ]);
    $valid = $this->createMockToken([
      'exp' => $timestamp + 100,
    ]);

    $sut = $this->getSut($valid, $timestamp, []);
    $this->assertEquals($valid, $sut->getToken());

    $state = $this->prophesize(StateInterface::class);
    $state
      ->get(TokenFactory::TOKEN_STATE)
      ->willReturn($expired);
    $state
      ->set(TokenFactory::TOKEN_STATE, Argument::exact($valid))
      ->shouldBeCalled();

    $sut = $this->getSut($state->reveal(), $timestamp, [new Response(body: $valid)]);
    $this->assertEquals($valid, $sut->getToken());
  }

  /**
   * Tests token validation.
   *
   * @param int $timestamp
   *   Test time.
   * @param \Psr\Http\Message\ResponseInterface|\GuzzleHttp\Exception\GuzzleException $response
   *   Response.
   */
  #[DataProvider('exceptionsData')]
  public function testExceptions(int $timestamp, ResponseInterface|GuzzleException $response): void {
    // First token is always expired which triggers token fetching.
    $expiredToken = $this->createMockToken([
      'exp' => $timestamp - 100,
    ]);

    $sut = $this->getSut($expiredToken, $timestamp, [$response]);
    $this->expectException(AlluException::class);
    $sut->getToken();
  }

  /**
   * Data provider for testExceptions().
   */
  public static function exceptionsData(): array {
    $timestamp = time();
    return [
      // Expired token.
      [
        $timestamp,
        new Response(body: self::createMockToken(['exp' => $timestamp - 100])),
      ],
      // Invalid payload.
      [
        $timestamp,
        new Response(body: self::createMockToken([])),
      ],
      // Malformed token.
      [
        $timestamp,
        new Response(body: '<html></html>'),
      ],
      // Failed response.
      [
        $timestamp,
        new ClientException(
          'test error',
          new Request('GET', '/test'),
          new Response(500),
        ),
      ],
    ];
  }

  /**
   * Get service under test.
   *
   * @param mixed|\Drupal\Core\State\StateInterface $token
   *   Current token.
   * @param int|TimeInterface $time
   *   Mocked current timestamp.
   * @param array $responses
   *   Http responses.
   */
  private function getSut(mixed $token, int|TimeInterface $time, array $responses): TokenFactory {
    if (!$token instanceof StateInterface) {
      $mock = $this->prophesize(StateInterface::class);
      $mock->get(TokenFactory::TOKEN_STATE)->willReturn($token);
      $token = $mock->reveal();
    }

    if (!$time instanceof TimeInterface) {
      $mock = $this->prophesize(TimeInterface::class);
      $mock->getCurrentTime()->willReturn($time);
      $time = $mock->reveal();
    }

    $client = $this->createMockHttpClient($responses);

    return new TokenFactory($time, $client, $token, new Settings('', '', ''));
  }

  /**
   * Gets mocked token.
   */
  private static function createMockToken(array $payload): string {
    $payload = base64_encode(json_encode($payload));

    return "foo.$payload.bar";
  }

}
