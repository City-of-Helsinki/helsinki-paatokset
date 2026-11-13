<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_allu\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\paatokset_allu\AlluException;
use Drupal\paatokset_allu\ApprovalType;
use Drupal\paatokset_allu\Client\Client;
use Drupal\paatokset_allu\Client\Settings;
use Drupal\paatokset_allu\Client\TokenFactory;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Allu api client test.
 */
#[Group('paatokset_allu')]
#[RunTestsInSeparateProcesses]
class ClientTest extends KernelTestBase {

  use ApiTestTrait;
  use ProphecyTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_api_base',
    'paatokset_allu',
    'diff',
  ];

  /**
   * Tests document fetching.
   */
  public function testDocument(): void {
    $sut = $this->getSut([
      new Response(body: 'Hello, world'),
      new Response(body: 'Hello, approval'),
      new Response(404, body: 'Not found'),
    ]);

    $this->assertEquals('Hello, world', (string) $sut->decisionDocument('123')->getBody());
    $this->assertEquals('Hello, approval', (string) $sut->approvalDocument('123', ApprovalType::WORK_FINISHED)->getBody());

    // API errors are converted to exceptions.
    $this->expectException(AlluException::class);
    $sut->decisionDocument('123');
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
