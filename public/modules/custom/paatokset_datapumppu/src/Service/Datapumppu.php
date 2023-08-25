<?php

namespace Drupal\paatokset_datapumppu\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drupal\node\NodeInterface;
use Psr\Log\LoggerInterface;

/**
 * Datapumppu API service.
 */
class Datapumppu {

  private const STATEMENTS_MIGRATION_ID = 'datapumppu_statements';

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
   * Aggregate statements made by trustee.
   *
   * @param \Drupal\node\NodeInterface $trustee
   *   The trustee node.
   * @param string $year
   *   Year to get statements from.
   * @param string $lang
   *   Get statements with this language.
   *
   * @return int
   *   The possible values are the RESULT_* constants defined in
   *   MigrationInterface.
   *
   * @see \Drupal\migrate\Plugin\MigrationInterface
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function aggregateStatements(NodeInterface $trustee, string $year, string $lang): int {
    $endpoint = $this->getStatementsEndpoint($trustee, $year, $lang);
    $configuration = [
      'source' => [
        'urls' => [$endpoint],
        'constants' => [
          'TRUSTEE_NID' => $trustee->id(),
          'LANGCODE' => $lang,
        ],
      ],
    ];

    $this->logger->info("Fetching from $endpoint");

    $migration = $this->migrationManager->createInstance(static::STATEMENTS_MIGRATION_ID, $configuration);
    if ($migration === FALSE) {
      return MigrationInterface::RESULT_DISABLED;
    }

    // Execute the migration.
    $executable = new MigrateExecutable($migration, new MigrateMessage());

    // Datapumppu API returns status code 200 event if a trustee with the given
    // name does not exist.
    return $executable->import();
  }

  /**
   * Get Datapummpu endpoint.
   *
   * @param \Drupal\node\NodeInterface $trustee
   *   The trustee node.
   * @param string $year
   *   Year to get statements from.
   * @param string $lang
   *   Langcode.
   *
   * @return string
   *   Endpoint url with correct URL parameters.
   */
  public function getStatementsEndpoint(NodeInterface $trustee, string $year, string $lang): string {
    $query = http_build_query([
      'name' => self::formatTrusteeName($trustee),
      'year' => $year,
      'lang' => $lang,
    ]);

    return "{$this->baseUrl}/api/statements?$query";
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
  private static function formatTrusteeName(NodeInterface $trustee): string {
    // It is not feasible to build trustee names that the Datapumppu API expects
    // from Ahjo data alone. If the field_datapumppu_id is set, use it so
    // the name guessing can be overwritten manually until a better solution is
    // found.
    // @todo link-to-ticket.
    if (!$trustee->get('field_trustee_datapumppu_id')->isEmpty()) {
      return $trustee->get('field_trustee_datapumppu_id')->getString();
    }

    return str_replace(',', '', $trustee->getTitle());
  }

}
