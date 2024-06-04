<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Drush\Commands;

use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drush\Attributes\Command;
use Drush\Attributes\Option;
use Drush\Attributes\Usage;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;

/**
 * Ahjo Organization Composition drush commands.
 *
 * @package Drupal\paatokset_ahjo_api\Commands
 */
final class AhjoOrgCompositionDrushCommands extends DrushCommands {

  use AutowireTrait;

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
    parent::__construct();
  }

  /**
   * Fetches all decisionmaker compositions, even for non active organizations.
   *
   * @return int
   *   The exit code.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  #[Command(name: 'org-composition:fetch-all', aliases: ['oc:all'])]
  #[Usage(name: 'drush org-composition:fetch-all', description: 'Retrieves all decisionmaker compositions.')]
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
   *   The command options.
   *
   * @return int
   *   The exit code.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  #[Command(name: 'org-composition:fetch-by-id', aliases: ['oc:id'])]
  #[Option(name: 'ids', description: 'Organization IDs, separated by comma.')]
  #[Usage(name: 'drush org-composition:fetch-by-id --ids=00400,02900', description: 'Fetches composition for city board and council only.')]
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
