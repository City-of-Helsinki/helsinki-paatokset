<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_datapumppu\Kernel\SourcePlugin;

use Drupal\KernelTests\KernelTestBase;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\paatokset_ahjo_api\Entity\Trustee;
use Drupal\paatokset_datapumppu\DatapumppuImportOptions;
use Drupal\paatokset_datapumppu\Plugin\migrate\source\DatapumppuStatementsSource;
use Drupal\paatokset_ahjo_api\Service\PolicymakerService;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;

/**
 * Tests datapumppu source plugin.
 */
class DatapumppuSourceTest extends KernelTestBase {

  use ProphecyTrait;
  use ApiTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_api_base',
    'paatokset_datapumppu',
    'migrate',
    'system',
  ];

  /**
   * The mocked migration.
   */
  private MigrationInterface|ObjectProphecy $migration;

  /**
   * The mocked policymaker service.
   */
  private MigrationInterface|ObjectProphecy $policymakerService;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->migration = $this->prophesize(MigrationInterface::class);
    $this->migration
      ->id()
      ->willReturn($this->randomMachineName());

    $this->migration
      ->getIdMap()
      ->willReturn($this->prophesize(MigrateIdMapInterface::class)->reveal());

    $this->migration
      ->id()
      ->willReturn($this->randomMachineName());

    $this->policymakerService = $this->prophesize(PolicymakerService::class);
    $this->container->set(PolicymakerService::class, $this->policymakerService->reveal());
  }

  /**
   * Tests source plugin with all dataset.
   */
  public function testLatestDataset(): void {
    $this->setupMockHttpClient([
      new Response(body: json_encode([
        [
          'startTime' => 'test-time',
          'expected_langcode' => 'fi',
        ],
      ])),
      new Response(body: json_encode([
        [
          'startTime' => 'test-time',
          'expected_langcode' => 'sv',
        ],
      ])),
    ]);

    $trustee = $this->mockTrustee('test-name');
    $this->policymakerService
      ->getTrustees(PolicymakerService::CITY_COUNCIL_DM_ID)
      ->willReturn([$trustee]);

    $plugin = $this->getPlugin([
      'url' => $this->randomMachineName(),
      'config' => (new DatapumppuImportOptions('latest', 2020, 2020))->toArray(),
    ]);

    // Collect iterator to array.
    $rows = iterator_to_array($plugin);

    $this->assertCount(2, $rows);
    foreach ($rows as $row) {
      $this->assertInstanceOf(Row::class, $row);
      $this->assertEquals('test-time', $row->getSourceProperty('startTime'));
      // Plugin makes two requests, one for fi endpoint and one for sv.
      $this->assertEquals($row->getSourceProperty('expected_langcode'), $row->getSourceProperty('langcode'));
    }
  }

  /**
   * Tests source plugin with all dataset / empty result.
   */
  public function testApiException(): void {
    $this->setupMockHttpClient([
      new RequestException('test-error', $this->prophesize(RequestInterface::class)->reveal()),
      new RequestException('test-error', $this->prophesize(RequestInterface::class)->reveal()),
    ]);

    $trustee = $this->mockTrustee('test-name');
    $this->policymakerService
      ->getTrustees(PolicymakerService::CITY_COUNCIL_DM_ID)
      ->willReturn([$trustee]);

    $logger = $this->prophesize(LoggerInterface::class);
    $logger->info(Argument::any());
    $logger
      ->warning(Argument::containingString('No results'))
      ->shouldBeCalled();

    $this->container->set('logger.channel.paatokset_datapumppu', $logger->reveal());

    $plugin = $this->getPlugin([
      'url' => $this->randomMachineName(),
      'config' => (new DatapumppuImportOptions('latest', 2020, 2020))->toArray(),
    ]);

    $this->assertEmpty(iterator_to_array($plugin));
  }

  /**
   * Gets mocked trustee.
   */
  private function mockTrustee(string $name): Trustee {
    $trustee = $this->prophesize(Trustee::class);
    $trustee
      ->id()
      ->willReturn($this->randomMachineName());
    $trustee
      ->getDatapumppuName()
      ->willReturn($name);

    return $trustee->reveal();
  }

  /**
   * Get plugin under test.
   */
  private function getPlugin(array $configuration): DatapumppuStatementsSource {
    /** @var \Drupal\migrate\Plugin\MigratePluginManagerInterface $sourcePluginManager */
    $sourcePluginManager = $this->container->get('plugin.manager.migrate.source');
    $instance = $sourcePluginManager->createInstance('datapumppu_statements', $configuration, $this->migration->reveal());
    $this->assertInstanceOf(DatapumppuStatementsSource::class, $instance);
    return $instance;
  }

}
