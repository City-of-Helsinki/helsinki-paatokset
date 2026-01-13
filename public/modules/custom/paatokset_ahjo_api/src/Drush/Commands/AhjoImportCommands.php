<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Drush\Commands;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\paatokset_ahjo_api\AhjoProxy\AhjoProxyClient;
use Drupal\paatokset_ahjo_api\Drush\Commands\DTO\MigrateSettings;
use Drupal\paatokset_ahjo_api\Queue\AhjoMigrationDriver;
use Drupal\paatokset_ahjo_api\Queue\AhjoQueue;
use Drupal\paatokset_ahjo_api\Queue\AhjoQueueManager;
use Drush\Attributes;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for importing data from Ahjo API.
 *
 * These commands replace `ahjo-proxy:*` commands with a
 * simpler implementation.
 *
 * @see \Drupal\paatokset_ahjo_proxy\Drush\Commands\AhjoProxyCommands
 */
class AhjoImportCommands extends DrushCommands {

  use AutowireTrait;

  public function __construct(
    private readonly AhjoMigrationDriver $migrationDriver,
    private readonly AhjoProxyClient $client,
    private readonly AhjoQueueManager $queue,
  ) {
    parent::__construct();
  }

  /**
   * Import cases from Ahjo.
   */
  #[Attributes\Command(name: 'ahjo-api:import:case')]
  #[Attributes\Help(description: 'Bulk import ahjo cases.')]
  #[Attributes\Option(name: 'idlist', description: 'Import comma separated list of entities.')]
  #[Attributes\Option(name: 'before', description: 'Search entities that are handled before this date.', suggestedValues: ['now'])]
  #[Attributes\Option(name: 'after', description: 'Search entities that are handled after this date.')]
  #[Attributes\Option(name: 'interval', description: 'Date interval for batching requests.', suggestedValues: ['P7D'])]
  #[Attributes\Option(name: 'update', description: 'Forces update of existing entities.')]
  #[Attributes\Option(name: 'queue', description: 'Add entities to the aggregation queue.')]
  #[Attributes\Usage(name: 'ahjo-api:import:case --idlist=hel-2020-000591,hel-2025-002349', description: 'Imports comma separated list of cases.')]
  public function runCaseMigration(
    array $options = [
      'after' => NULL,
      'before' => NULL,
      'interval' => NULL,
      'idlist' => '',
      'update' => FALSE,
      'queue' => FALSE,
    ],
  ): int {
    $settings = MigrateSettings::fromOptions($options);

    if ($settings->queue) {
      $cases = $this->client->getCases(
        'fi',
        (new \DateTimeImmutable($settings->after ?? '-1 week'))
          ->setTime(0, 0),
        (new \DateTimeImmutable($settings->before ?? 'now'))
          ->modify('+1 day')
          ->setTime(0, 0),
        new \DateInterval($settings->interval ?? 'P7D'),
      );

      foreach ($cases as $case) {
        $this->queue->addItemToAhjoQueue(AhjoQueue::AggregationQueue, $case->id, 'ahjo_cases_v2');
      }

      return self::EXIT_SUCCESS;
    }

    if ($this->migrationDriver->import($settings, 'ahjo_cases_v2') !== MigrationInterface::RESULT_COMPLETED) {
      return self::EXIT_FAILURE;
    }

    return self::EXIT_SUCCESS;
  }

}
