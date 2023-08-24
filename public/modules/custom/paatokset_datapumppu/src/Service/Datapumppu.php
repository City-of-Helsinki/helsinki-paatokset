<?php

namespace Drupal\paatokset_datapumppu\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drupal\node\NodeInterface;
use Psr\Log\LoggerInterface;

/**
 * Datapumppu API service.
 */
class Datapumppu {

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * Migration manager.
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManager
   */
  protected MigrationPluginManagerInterface $migrationManager;

  /**
   * Datapumppu API base url.
   *
   * @var string
   */
  protected string $baseUrl;

  /**
   * Constructs Datapumppu service.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   Logger service.
   * @param \Drupal\migrate\Plugin\MigrationPluginManager $migration_manager
   *   The migration manager.
   * @param string $base_url
   *   Datapumppu API base url.
   */
  public function __construct(LoggerChannelFactoryInterface $logger_factory, MigrationPluginManagerInterface $migration_manager, string $base_url) {
    $this->logger = $logger_factory->get('paatokset_datapumppu');
    $this->migrationManager = $migration_manager;
    $this->baseUrl = $base_url;
  }

  /**
   * Aggregate statements for trustee.
   *
   * @param \Drupal\node\NodeInterface $trustee
   *   The trustee node.
   * @param string $year
   *   Year to get statements from.
   *
   * @return int
   *   0 on success.
   */
  public function aggregateStatements(NodeInterface $trustee, string $year): int {
    $migration_id = 'datapumppu_statements';
    $query = http_build_query([
      'name' => static::getTrusteeName($trustee),
      'year' => $year,
      'lang' => 'fi',
    ]);
    $endpoint = "{$this->baseUrl}/api/statements?$query";

    $this->logger->info("Fetching from $endpoint");

    $migration = $this->migrationManager->createInstance($migration_id, [
      'source' => [
        'constants' => [
          'trustee_nid' => $trustee->id(),
        ],
        'urls' => [
          $endpoint,
        ],
      ],
    ]);

    if ($migration === FALSE) {
      return 6;
    }

    // Execute migration.
    $executable = new MigrateExecutable($migration, new MigrateMessage());
    $status = $executable->import();
    return $status;
  }

  /**
   * Format trustee title to the format that Datapumppu expects.
   *
   * E.g. 'Arhinmäki, Paavo' -> 'Arhinmäki Paavo'.
   *
   * @param \Drupal\node\NodeInterface $trustee
   *   The trustee node.
   *
   * @return string
   *   The title transformed into name string
   */
  private static function getTrusteeName(NodeInterface $trustee): string {
    $title = $trustee->getTitle();
    $nameParts = explode(',', $title);
    if (isset($nameParts[1])) {
      return trim($nameParts[0]) . ' ' . trim($nameParts[1]);
    }
    else {
      return $nameParts[0];
    }
  }

}
