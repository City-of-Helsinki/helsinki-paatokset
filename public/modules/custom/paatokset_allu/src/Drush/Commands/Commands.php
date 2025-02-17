<?php

declare(strict_types=1);

namespace Drupal\paatokset_allu\Drush\Commands;

use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drush\Attributes;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;

/**
 * Allu Aggregator drush commands.
 */
final class Commands extends DrushCommands {

  use AutowireTrait;

  /**
   * Constructor for Commands.
   *
   * @param \Drupal\migrate\Plugin\MigrationPluginManager $migrationManager
   *   The migration manager.
   */
  public function __construct(
    private readonly MigrationPluginManagerInterface $migrationManager,
  ) {
    parent::__construct();
  }

  /**
   * Aggregates allu documents.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  #[Attributes\Command(name: 'allu:run-allu-migration')]
  #[Attributes\Help(description: 'Bulk import allu documents.')]
  #[Attributes\Argument(name: 'id', description: 'Allu document type')]
  #[Attributes\Option(name: 'after', description: 'Get documents starting from specific time.')]
  #[Attributes\Option(name: 'before', description: 'Get documents until specific time.')]
  #[Attributes\Option(name: 'update', description: 'Update previously-imported items with the current data.', suggestedValues: [TRUE])]
  #[Attributes\Usage(name: 'allu:run-allu-migration allu_decisions --after="-1 year" --update', description: 'Updates all decisions within a year.')]
  public function runMigration(
    string $id,
    array $options = [
      'after' => NULL,
      'before' => NULL,
      'update' => FALSE,
    ],
  ): int {
    try {
      $after = new \DateTimeImmutable($options['after'] ?? '-1 year');
      $before = new \DateTimeImmutable($options['before'] ?? 'now');
    }
    catch (\DateMalformedStringException $e) {
      $this->io->error("Invalid date : {$e->getMessage()}");
      return self::EXIT_FAILURE;
    }

    $configuration = [
      'source' => [
        'after' => $after->format(\DateTimeInterface::RFC3339),
        'before' => $before->format(\DateTimeInterface::RFC3339),
      ],
    ];

    /** @var \Drupal\migrate\Plugin\MigrationInterface|false $migration */
    $migration = $this->migrationManager->createInstance($id, $configuration);
    if ($migration === FALSE) {
      $this->io()->error("Failed to create migration $id");
      return self::EXIT_FAILURE;
    }

    if ($migration->getStatus() !== MigrationInterface::STATUS_IDLE) {
      $this->io()->warning("Migration is running. Consider: drush migrate-reset-status $id");
      return self::EXIT_FAILURE;
    }

    // Update existing entities.
    if ($options['update']) {
      $migration->getIdMap()->prepareUpdate();
    }

    // Execute the migration.
    $executable = new MigrateExecutable($migration, new MigrateMessage());

    if ($executable->import() === MigrationInterface::RESULT_COMPLETED) {
      return self::EXIT_SUCCESS;
    }

    return self::EXIT_FAILURE;
  }

}
