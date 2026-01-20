<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Queue;

use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drupal\paatokset_ahjo_api\Drush\Commands\DTO\MigrateSettings;

/**
 * Service for executing Ahjo migrations.
 */
class AhjoMigrationDriver {

  public function __construct(
    private readonly MigrationPluginManagerInterface $migrationManager,
  ) {}

  /**
   * Executes a migration with the given settings.
   *
   * @param \Drupal\paatokset_ahjo_api\Drush\Commands\DTO\MigrateSettings $settings
   *   Migration settings.
   * @param string $migrationId
   *   Migration ID to execute.
   *
   * @return int
   *   MigrationInterface::RESULT_* constant.
   */
  public function import(MigrateSettings $settings, string $migrationId): int {
    /** @var \Drupal\migrate\Plugin\MigrationInterface|false $migration */
    $migration = $this->migrationManager->createInstance($migrationId, [
      'source' => $settings->toSourceConfiguration(),
    ]);

    if ($migration === FALSE) {
      throw new \RuntimeException("Failed to create migration: $migrationId");
    }

    if ($migration->getStatus() !== MigrationInterface::STATUS_IDLE) {
      throw new \RuntimeException("Migration $migrationId is already running. Consider running: drush migrate-reset-status $migrationId");
    }

    // Update existing entities.
    if ($settings->update && $settings->idlist) {
      $migration->getIdMap()->setUpdate($settings->idlist);
    }

    $executable = new MigrateExecutable($migration, new MigrateMessage());
    return $executable->import();
  }

}
