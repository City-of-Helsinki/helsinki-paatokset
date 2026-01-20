<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\Commands;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\paatokset_ahjo_api\AhjoProxy\AhjoProxyClientInterface;
use Drupal\paatokset_ahjo_api\AhjoProxy\DTO\AhjoCase;
use Drupal\paatokset_ahjo_api\Drush\Commands\AhjoImportCommands;
use Drupal\paatokset_ahjo_api\Queue\AhjoMigrationDriver;
use Drupal\paatokset_ahjo_api\Queue\AhjoQueue;
use Drupal\paatokset_ahjo_api\Queue\AhjoQueueManager;
use Drupal\Tests\paatokset_ahjo_api\Kernel\KernelTestBase;
use Prophecy\Argument;

/**
 * Tests ahjo import commands.
 */
class AhjoImportCommandsTest extends KernelTestBase {

  /**
   * Tests case migration.
   */
  public function testRunCaseMigration(): void {
    $migrationDriver = $this->prophesize(AhjoMigrationDriver::class);
    $client = $this->prophesize(AhjoProxyClientInterface::class);
    $queue = $this->prophesize(AhjoQueueManager::class);

    $migrationDriver->import(Argument::any(), 'ahjo_cases_v2')
      ->shouldBeCalled()
      ->willReturn(MigrationInterface::RESULT_COMPLETED);

    $sut = new AhjoImportCommands(
      $migrationDriver->reveal(),
      $client->reveal(),
      $queue->reveal(),
    );

    $this->assertEquals(AhjoImportCommands::EXIT_SUCCESS, $sut->runCaseMigration());

    $migrationDriver->import(Argument::any(), 'ahjo_cases_v2')
      ->shouldBeCalled()
      ->willReturn(MigrationInterface::RESULT_FAILED);

    $sut = new AhjoImportCommands(
      $migrationDriver->reveal(),
      $client->reveal(),
      $queue->reveal(),
    );

    $this->assertEquals(AhjoImportCommands::EXIT_FAILURE, $sut->runCaseMigration());
  }

  /**
   * Tests case migration with queue option.
   */
  public function testRunCaseMigrationWithQueue(): void {
    $migrationDriver = $this->prophesize(AhjoMigrationDriver::class);
    $client = $this->prophesize(AhjoProxyClientInterface::class);
    $queueManager = $this->prophesize(AhjoQueueManager::class);

    $case1 = new AhjoCase(
      'HEL-2025-000001',
      'HEL 2025-000001',
      'Test case 1',
      new \DateTimeImmutable('2025-01-01'),
      new \DateTimeImmutable('2025-01-01'),
      '01 00',
      'Test classification',
      'Open',
      'fi',
      'Public',
      [],
      [],
      [],
    );

    $case2 = new AhjoCase(
      'HEL-2025-000002',
      'HEL 2025-000002',
      'Test case 2',
      new \DateTimeImmutable('2025-01-02'),
      new \DateTimeImmutable('2025-01-02'),
      '01 00',
      'Test classification',
      'Open',
      'fi',
      'Public',
      [],
      [],
      [],
    );

    $client->getCases('fi', Argument::type(\DateTimeImmutable::class), Argument::type(\DateTimeImmutable::class), Argument::type(\DateInterval::class))
      ->shouldBeCalled()
      ->willReturn(new \ArrayIterator([$case1, $case2]));

    $queueManager->addItemToAhjoQueue(AhjoQueue::AggregationQueue, 'HEL-2025-000001', 'ahjo_cases_v2')
      ->shouldBeCalled();
    $queueManager->addItemToAhjoQueue(AhjoQueue::AggregationQueue, 'HEL-2025-000002', 'ahjo_cases_v2')
      ->shouldBeCalled();

    // Migration driver should not be called when queue option is set.
    $migrationDriver->import(Argument::any(), Argument::any())
      ->shouldNotBeCalled();

    $sut = new AhjoImportCommands(
      $migrationDriver->reveal(),
      $client->reveal(),
      $queueManager->reveal(),
    );

    $result = $sut->runCaseMigration([
      'after' => '2025-01-01',
      'before' => '2025-01-08',
      'interval' => 'P7D',
      'idlist' => '',
      'update' => FALSE,
      'queue' => TRUE,
    ]);

    $this->assertEquals(AhjoImportCommands::EXIT_SUCCESS, $result);
  }

}
