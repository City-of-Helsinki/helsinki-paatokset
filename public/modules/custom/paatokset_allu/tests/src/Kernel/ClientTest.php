<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_allu\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\paatokset_allu\Client\Client;
use Drupal\paatokset_allu\Client\Settings;
use Drupal\paatokset_allu\Client\TokenFactory;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use GuzzleHttp\Psr7\Response;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Allu api client test.
 *
 * @group paatokset_allu
 */
class ClientTest extends KernelTestBase {

  use ApiTestTrait;
  use ProphecyTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_api_base',
    'paatokset_allu',
  ];

  /**
   * Tests response streaming.
   */
  public function testResponseStreaming(): void {
    $sut = $this->getSut([
      new Response(body: 'Hello, world'),
    ]);

    $response = $sut->streamDecision('123');
    $this->assertInstanceOf(StreamedResponse::class, $response);

    ob_start();
    $response->sendContent();
    $content = ob_get_clean();

    $this->assertStringContainsString('Hello, world', $content);
  }

  /**
   * Gets service under test.
   *
   * @param array $responses
   *   The expected responses.
   *
   * @return \Drupal\paatokset_allu\Client\Client
   *   Allu client.
   */
  private function getSut(array $responses): Client {
    $tokenFactory = $this->prophesize(TokenFactory::class);
    $tokenFactory
      ->getToken()
      ->willReturn($this->randomMachineName());

    return new Client(
      $this->createMockHttpClient($responses),
      $tokenFactory->reveal(),
      new Settings('', '', '')
    );
  }

}
