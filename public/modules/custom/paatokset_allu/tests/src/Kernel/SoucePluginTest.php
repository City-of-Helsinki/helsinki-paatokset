<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_allu\Kernel;

use Drupal\paatokset_allu\Client\Settings;
use Drupal\paatokset_allu\Client\TokenFactory;
use Drupal\paatokset_allu\DecisionType;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\migrate\Kernel\MigrateSourceTestBase;
use GuzzleHttp\Psr7\Response;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests migrate source plugin.
 *
 * @covers \Drupal\paatokset_allu\Plugin\migrate\source\AlluSource
 */
class SoucePluginTest extends MigrateSourceTestBase {

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
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    // Source plugin makes three request for each Decision type.
    // - once for counting.
    // - once for receiving the data.
    // - once for testing skipping.
    $decisions = array_merge(self::getTestDecisions(), self::getTestDecisions(), self::getTestDecisions());
    $decisionResponses = array_map(static fn (array $decision) => new Response(body: json_encode([$decision])), $decisions);

    $this->setupMockHttpClient($decisionResponses);

    $tokenFactory = $this->prophesize(TokenFactory::class);
    $tokenFactory
      ->getToken()
      ->willReturn('123');

    $this->container->set(TokenFactory::class, $tokenFactory->reveal());
    $this->container->set(Settings::class, new Settings('', '', 'https://example.com'));
  }

  /**
   * Data provider for test.
   */
  public function providerSource(): array {
    $decisions = $this->getTestDecisions();
    $decisionConfiguration = [
      'ids' => [
        'id' => [
          'type' => 'string',
        ],
      ],
      'fields' => [
        [
          'name' => 'id',
          'selector' => 'id',
        ],
        [
          'name' => 'blaa',
          'selector' => 'applicationId',
        ],
      ],
    ];

    return [
      [
        // Source data is not used, set up is done in setUp().
        [],
        // Expected only contains specified fields.
        array_map(static fn (array $decision) => [
          'id' => $decision['id'],
          'blaa' => $decision['applicationId'],
        ], $decisions),
        NULL,
        $decisionConfiguration,
      ],
    ];
  }

  /**
   * Get test decisions.
   */
  public static function getTestDecisions(): array {
    $cases = DecisionType::cases();

    return array_map(
      static fn (DecisionType $type, int $idx) => [
        'id' => $idx,
        'applicationId' => 'KP2401776',
        'address' => 'Kaivokatu 10',
        'decisionMakerName' => 'Testi Testaaja',
        'decisionDate' => '2024-06-06T06:49:38.068151Z',
      ],
      $cases,
      array_keys($cases),
    );
  }

}
