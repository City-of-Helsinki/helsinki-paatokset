<?php

declare(strict_types=1);

namespace Drupal\paatokset_datapumppu\Drush\Commands;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drupal\paatokset_datapumppu\DatapumppuImportOptions;
use Drupal\paatokset_datapumppu\Entity\Statement;
use Drush\Attributes;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;

/**
 * Datapumppu Aggregator drush commands.
 */
final class DatapumppuCommands extends DrushCommands {

  use AutowireTrait;

  private const STATEMENTS_MIGRATION_ID = 'datapumppu_statements';

  /**
   * Constructor for Datapumppu Aggregator Commands.
   *
   * @param \Drupal\migrate\Plugin\MigrationPluginManager $migrationManager
   *   The migration manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    private readonly MigrationPluginManagerInterface $migrationManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct();
  }

  /**
   * Aggregates statements of all trustees from Datapumppu API.
   *
   * This command does not consider meeting data.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  #[Attributes\Command(name: 'datapumppu:all-trustee-statements')]
  #[Attributes\Help(description: 'Fix mistakes in the database. For normal imports, run datapumppu_statements migration directly.')]
  #[Attributes\Option(name: 'start-year', description: 'Get statements starting from specific year (default is current year).')]
  #[Attributes\Option(name: 'year', description: 'Get statements from specific year.')]
  #[Attributes\Option(name: 'trustee', description: 'Get statements for specific trustee (default is all trustees).')]
  #[Attributes\Option(name: 'sync', description: 'Remove statements that are not present in the database.', suggestedValues: [TRUE])]
  #[Attributes\Option(name: 'update', description: 'Update previously-imported items with the current data.', suggestedValues: [TRUE])]
  #[Attributes\Usage(name: 'datapumppu:all-trustee-statements', description: 'Retrieves trustee statements from current year.')]
  #[Attributes\Usage(name: 'datapumppu:all-trustee-statements --start-year=2020', description: 'Retries all trustee statements starting from a specific year.')]
  #[Attributes\Usage(name: 'datapumppu:all-trustee-statements --trustee="Mehiläinen Maija"', description: 'Retrieves statements made by specified trustee.')]
  #[Attributes\Usage(name: 'datapumppu:all-trustee-statements --year=2020 --sync', description: 'Deletes statements made in 2020 and and re-retrieves them.')]
  public function getAllTrusteeStatements(
    array $options = [
      'start-year' => NULL,
      'year' => NULL,
      'trustee' => NULL,
      'sync' => FALSE,
      'update' => TRUE,
    ],
  ): int {
    $importOptions = DatapumppuImportOptions::fromOptions($options);

    if ($importOptions->sync) {
      $this->removePreviousStatements($importOptions);
    }

    $configuration = [
      'source' => [
        'trustees' => 'all',
        'config' => $importOptions->toArray(),
      ],
    ];

    /** @var \Drupal\migrate\Plugin\MigrationInterface|false $migration */
    $migration = $this->migrationManager->createInstance(self::STATEMENTS_MIGRATION_ID, $configuration);
    if ($migration === FALSE) {
      return self::EXIT_FAILURE;
    }

    if ($migration->getStatus() !== MigrationInterface::STATUS_IDLE) {
      $this->logger->warning("Migration is running. Consider: drush migrate-reset-status " . self::STATEMENTS_MIGRATION_ID);
    }

    // Update existing entities.
    if ($importOptions->update) {
      $migration->getIdMap()->prepareUpdate();
    }

    // Execute the migration.
    $executable = new MigrateExecutable($migration, new MigrateMessage());

    if ($executable->import() === MigrationInterface::RESULT_COMPLETED) {
      return self::EXIT_SUCCESS;
    }

    return self::EXIT_FAILURE;
  }

  /**
   * Remove previous statements before import.
   */
  private function removePreviousStatements(DatapumppuImportOptions $options): void {
    $yearStart = DrupalDateTime::createFromArray(['year' => $options->startYear, 'month' => 1, 'day' => 1]);
    $yearEnd = DrupalDateTime::createFromArray(['year' => $options->endYear, 'month' => 12, 'day' => 31]);
    $yearEnd->setTime(23, 59, 59);
    $between = [
      $yearStart->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
      $yearEnd->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
    ];

    $storage = $this->entityTypeManager->getStorage('paatokset_statement');
    $ids = $storage
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('start_time', $between, 'BETWEEN')
      ->execute();

    foreach ($ids as $id) {
      $statement = $storage->load($id);
      assert($statement instanceof Statement);

      // Optionally filter by trustee name.
      if ($options->trustee && $statement->getSpeaker()?->getDatapumppuName() !== $options->trustee) {
        continue;
      }

      $statement->delete();
    }
  }

}
