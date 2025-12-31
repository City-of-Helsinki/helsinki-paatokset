<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_allu\Unit;

use Drupal\KernelTests\KernelTestBase;
use Drupal\paatokset_allu\AlluException;
use Drupal\paatokset_allu\ApprovalType;
use Drupal\paatokset_allu\Client\Client;
use Drupal\paatokset_allu\Client\Settings;
use Drupal\paatokset_allu\Client\TokenFactory;
use Drupal\paatokset_allu\DecisionType;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Group;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Http\Message\RequestInterface;

/**
 * Allu api client test.
 */
#[Group('paatokset_allu')]
class ClientTest extends KernelTestBase {

  use ApiTestTrait;
  use ProphecyTrait;

  /**
   * Tests document search.
   */
  public function testDocuments(): void {
    $decision = [
      'id' => 2145,
      'applicationId' => 'KP2401776',
      'address' => 'Kaivokatu 10',
      'decisionMakerName' => 'Testi Testaaja',
      'decisionDate' => '2024-06-06T06:49:38.068151Z',
    ];
    $approval = [
      'id' => 2270,
      'applicationId' => 'KP2500001',
      'address' => 'Testiosoite 1',
      'type' => 'OPERATIONAL_CONDITION',
      'approvalDate' => '2025-01-27T12:38:13.597Z',
    ];

    $sut = $this->getSut([
      new Response(body: json_encode([$decision, $decision])),
      new Response(body: json_encode([$approval])),
    ]);

    $response = $sut->decisions(DecisionType::EVENT, new \DateTimeImmutable('-1 week'), new \DateTimeImmutable('now'));
    $this->assertIsArray($response);
    $this->assertCount(2, $response);
    foreach ($response as $document) {
      $this->assertSame($document + ['type' => DecisionType::EVENT->value], $document);
    }

    $response = $sut->approvals(ApprovalType::OPERATIONAL_CONDITION, new \DateTimeImmutable('-1 week'), new \DateTimeImmutable('now'));
    $this->assertIsArray($response);
    $this->assertCount(1, $response);
    foreach ($response as $document) {
      $this->assertSame($approval, $document);
    }
  }

  /**
   * Tests decision request exceptions.
   */
  public function testDecisionExceptions(): void {
    $sut = $this->getSut([
      new BadResponseException("test error", $this->prophesize(RequestInterface::class)->reveal(), new Response(500)),
    ]);

    $this->expectException(AlluException::class);
    $sut->decisions(DecisionType::EVENT, new \DateTimeImmutable('-1 week'), new \DateTimeImmutable('now'));
  }

  /**
   * Tests approval request exceptions.
   */
  public function testApprovalExceptions(): void {
    $sut = $this->getSut([
      new BadResponseException("test error", $this->prophesize(RequestInterface::class)->reveal(), new Response(500)),
    ]);

    $this->expectException(AlluException::class);
    $sut->approvals(ApprovalType::OPERATIONAL_CONDITION, new \DateTimeImmutable('-1 week'), new \DateTimeImmutable('now'));
  }

  /**
   * Creates service.
   *
   * @param array $responses
   *   Mock responses.
   *
   * @return \Drupal\paatokset_allu\Client\Client
   *   Test client.
   */
  private function getSut(array $responses): Client {
    $client = $this->createMockHttpClient($responses);
    $tokenFactory = $this->prophesize(TokenFactory::class);
    $tokenFactory->getToken()->willReturn('123');
    return new Client($client, $tokenFactory->reveal(), new Settings('', '', ''));
  }

}
