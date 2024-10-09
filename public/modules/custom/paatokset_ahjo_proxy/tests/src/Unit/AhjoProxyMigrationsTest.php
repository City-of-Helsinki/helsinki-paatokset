<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_proxy\Unit;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\file\FileRepositoryInterface;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drupal\paatokset_ahjo_openid\AhjoOpenId;
use Drupal\paatokset_ahjo_proxy\AhjoProxy;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\LoggerInterface;

/**
 * Tests ahjo proxy migrations test.
 *
 * @group paatokset_ahjo_proxy
 */
class AhjoProxyMigrationsTest extends UnitTestCase {

  use ProphecyTrait;
  use ApiTestTrait;

  /**
   * {@inheritDoc}
   */
  public function setUp(): void {
    parent::setUp();

    putenv('AHJO_PROXY_BASE_URL=https://ahjo-url');
  }

  /**
   * Tests migration ids.
   *
   * @dataProvider provideMigrationIds
   */
  public function testSingleMigrations(string $type, int $rval, ?array $pluginArguments = NULL): void {
    $client = $this->createMockHttpClient([
      new Response(200, body: json_encode([
        'hello' => 'world',
      ])),
    ]);

    $migrationPluginManager = $this->prophesize(MigrationPluginManagerInterface::class);
    if ($pluginArguments) {
      [$pluginId, $url] = $pluginArguments;
      $migrationPluginManager
        ->createInstance(Argument::exact($pluginId), Argument::exact([
          'source' => [
            'urls' => [$url],
          ],
        ]))
        ->shouldBeCalled()
        // The mock migration cannot be executed in a unit test.
        ->willReturn(NULL);
    }

    $sut = $this->getSut(client: $client, migrationPluginManager: $migrationPluginManager->reveal());

    // Should return 6 when migration plugin creation fails.
    $this->assertEquals($rval, $sut->migrateSingleEntity($type, '123'));
  }

  /**
   * Data provider for test.
   */
  private function provideMigrationIds(): array {
    return [
      // Migration uses proxy url.
      ['meetings', 6, ['ahjo_meetings:single', 'https://ahjo-url/ahjo-proxy/meetings/single/123']],
      ['trustees_sv', 6, ['ahjo_trustees:single_sv', 'https://ahjo-url/ahjo-proxy/trustees/single/123?apireqlang=sv']],
      ['unknown', 5],
    ];
  }

  /**
   * Gets service under test.
   */
  private function getSut(
    ?ClientInterface $client = NULL,
    ?CacheBackendInterface $cache = NULL,
    ?EntityTypeManagerInterface $entityTypeManager = NULL,
    ?MigrationPluginManagerInterface $migrationPluginManager = NULL,
    ?LoggerInterface $logger = NULL,
    ?MessengerInterface $messenger = NULL,
    ?FileRepositoryInterface $fileRepository = NULL,
    ?ConfigFactoryInterface $configFactory = NULL,
    ?Connection $connection = NULL,
    ?QueueFactory $queueFactory = NULL,
    ?AhjoOpenId $ahjoOpenId = NULL,
  ): AhjoProxy {
    if (is_null($client)) {
      $client = $this->prophesize(ClientInterface::class);
      $client = $client->reveal();
    }

    if (is_null($cache)) {
      $cache = $this->prophesize(CacheBackendInterface::class);
      $cache = $cache->reveal();
    }

    if (is_null($entityTypeManager)) {
      $entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
      $entityTypeManager = $entityTypeManager->reveal();
    }

    if (is_null($migrationPluginManager)) {
      $migrationPluginManager = $this->prophesize(MigrationPluginManagerInterface::class);
      $migrationPluginManager = $migrationPluginManager->reveal();
    }

    if (is_null($logger)) {
      $logger = $this->prophesize(LoggerInterface::class);
      $logger = $logger->reveal();
    }

    if (is_null($messenger)) {
      $messenger = $this->prophesize(MessengerInterface::class);
      $messenger = $messenger->reveal();
    }

    if (is_null($fileRepository)) {
      $fileRepository = $this->prophesize(FileRepositoryInterface::class);
      $fileRepository = $fileRepository->reveal();
    }

    if (is_null($configFactory)) {
      $configFactory = $this->prophesize(ConfigFactoryInterface::class);
      $configFactory = $configFactory->reveal();
    }

    if (is_null($connection)) {
      $connection = $this->prophesize(Connection::class);
      $connection = $connection->reveal();
    }

    if (is_null($queueFactory)) {
      $queueFactory = $this->prophesize(QueueFactory::class);
      $queueFactory = $queueFactory->reveal();
    }

    if (is_null($ahjoOpenId)) {
      $ahjoOpenId = $this->prophesize(AhjoOpenId::class);
      $ahjoOpenId = $ahjoOpenId->reveal();
    }

    return new AhjoProxy(
      $client,
      $cache,
      $entityTypeManager,
      $migrationPluginManager,
      $logger,
      $messenger,
      $fileRepository,
      $configFactory,
      $connection,
      $queueFactory,
      $ahjoOpenId,
    );
  }

}
