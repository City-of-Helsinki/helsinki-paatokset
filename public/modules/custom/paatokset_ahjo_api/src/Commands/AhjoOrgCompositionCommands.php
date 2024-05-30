<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Commands;

use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drush\Commands\DrushCommands;

/**
 * Ahjo Organization Composition drush commands.
 *
 * @package Drupal\paatokset_ahjo_api\Commands
 */
final class AhjoOrgCompositionCommands extends DrushCommands {
  private const COMPOSITION_MIGRATION_ID = 'ahjo_org_composition';

  /**
   * Constructor for Ahjo Organization Composition Commands.
   *
   * @param \Drupal\migrate\Plugin\MigrationPluginManager $migrationManager
   *   The migration manager.
   */
  public function __construct(
    private MigrationPluginManagerInterface $migrationManager
  ) {
  }

  /**
   * Fetches all decisionmaker compositions, even for non active organizations.
   *
   * @command org-composition:fetch-all
   *
   * @usage org-composition:fetch-all
   *   Retrieves all decisionmaker compositions.
   *
   * @aliases oc:all
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getAllOrgCompositions(): int {
    $configuration = [
      'source' => [
        'orgs' => 'all',
      ],
    ];

    $this->logger->info("Running org composition migration for all orgs.");

    return $this->runMigration($configuration);
  }

  /**
   * Fetches decisionmaker compositions by ID.
   *
   * @param array $options
   *   Additional options for the command.
   *
   * @command org-composition:fetch-by-id
   *
   * @option ids
   *   Organization IDs, separated by comma.
   *
   * @usage org-composition:fetch-by-id --ids=00400,02900
   *   Fetches composition for city board and council only.
   *
   * @aliases oc:id
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getOrgCompositionsById(array $options = [
    'ids' => NULL,
  ]): int {
    $configuration = [
      'source' => [
        'orgs' => 'ids',
        'org_ids' => $options['ids'],
      ],
    ];

    $this->logger->info("Running org composition migration for: {$options['ids']}");

    return $this->runMigration($configuration);
  }

  /**
   * Run migration.
   *
   * @param array $configuration
   *   Configuration for migration.
   *
   * @return int
   *   Migration status.
   */
  private function runMigration(array $configuration): int {
    /** @var \Drupal\migrate\Plugin\MigrationInterface|false $migration */
    $migration = $this->migrationManager->createInstance(self::COMPOSITION_MIGRATION_ID, $configuration);
    if ($migration === FALSE) {
      return self::EXIT_FAILURE;
    }

    if ($migration->getStatus() !== MigrationInterface::STATUS_IDLE) {
      $this->logger->warning("Migration is running. Consider: drush migrate-reset-status " . static::COMPOSITION_MIGRATION_ID);
    }

    // Always update entities.
    $migration->getIdMap()->prepareUpdate();

    // Execute the migration.
    $executable = new MigrateExecutable($migration, new MigrateMessage());

    if ($executable->import() === MigrationInterface::RESULT_COMPLETED) {
      $this->logger->info('Migration completed!');
      return self::EXIT_SUCCESS;
    }

    $this->logger->warning('Migration did not complete successfully.');
    return self::EXIT_FAILURE;
  }

}
