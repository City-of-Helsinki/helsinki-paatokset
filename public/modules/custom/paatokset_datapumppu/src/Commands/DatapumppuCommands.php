<?php

declare(strict_types = 1);

namespace Drupal\paatokset_datapumppu\Commands;

use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drush\Commands\DrushCommands;

/**
 * Datapumppu Aggregator drush commands.
 *
 * @package Drupal\paatokset_datapumppu\Commands
 */
final class DatapumppuCommands extends DrushCommands {
  private const STATEMENTS_MIGRATION_ID = 'datapumppu_statements';

  /**
   * Constructor for Datapumppu Aggregator Commands.
   *
   * @param \Drupal\migrate\Plugin\MigrationPluginManager $migrationManager
   *   The migration manager.
   */
  public function __construct(
    private MigrationPluginManagerInterface $migrationManager
  ) {
  }

  /**
   * Aggregates statements of all trustees from Datapumppu API.
   *
   * This command does not consider meeting data.
   *
   * @param array $options
   *   Additional options for the command.
   *
   * @command datapumppu:all-trustee-statements
   *
   * @option start-year
   *   Get statements starting from specific year (default is current year).
   *
   * @usage datapumppu:all-trustee-statements
   *   Retrieves trustee statements from current year.
   * @usage datapumppu:all-trustee-statements --start-year=2020
   *   Retries all trustee statements starting from a specific year.
   *
   * @aliases dp:all-statements
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getAllTrusteeStatements(array $options = [
    'start-year' => NULL,
  ]): int {
    if (is_numeric($options['start-year'])) {
      $startYear = (int) $options['start-year'];
    }
    else {
      $startYear = (int) date("Y");
    }

    $configuration = [
      'source' => [
        'trustees' => 'all',
        'start_year' => $startYear,
      ],
    ];

    /** @var \Drupal\migrate\Plugin\MigrationInterface|false $migration */
    $migration = $this->migrationManager->createInstance(self::STATEMENTS_MIGRATION_ID, $configuration);
    if ($migration === FALSE) {
      return self::EXIT_FAILURE;
    }

    if ($migration->getStatus() !== MigrationInterface::STATUS_IDLE) {
      $this->logger->warning("Migration is running. Consider: drush migrate-reset-status " . static::STATEMENTS_MIGRATION_ID);
    }

    // Execute the migration.
    $executable = new MigrateExecutable($migration, new MigrateMessage());

    if ($executable->import() === MigrationInterface::RESULT_COMPLETED) {
      return self::EXIT_SUCCESS;
    }

    return self::EXIT_FAILURE;
  }

}
