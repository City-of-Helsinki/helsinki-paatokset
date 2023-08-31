<?php

declare(strict_types = 1);

namespace Drupal\paatokset_datapumppu\Commands;

use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\node\NodeInterface;
use Drupal\paatokset_datapumppu\Service\Datapumppu;
use Drupal\paatokset_policymakers\Service\PolicymakerService;
use Drush\Commands\DrushCommands;

/**
 * Datapumppu Aggregator drush commands.
 *
 * @package Drupal\paatokset_datapumppu\Commands
 */
class DatapumppuCommands extends DrushCommands {

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Node storage service.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $nodeStorage;

  /**
   * Entity memory cache.
   *
   * @var \Drupal\Core\Cache\MemoryCache\MemoryCacheInterface
   */
  protected MemoryCacheInterface $memoryCache;

  /**
   * Meeting service.
   *
   * @var \Drupal\paatokset_policymakers\Service\PolicymakerService
   */
  protected PolicymakerService $policymakerService;

  /**
   * Datapumppu service.
   *
   * @var \Drupal\paatokset_datapumppu\Service\Datapumppu
   */
  protected Datapumppu $datapumppu;

  /**
   * Constructor for Datapumppu Aggregator Commands.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   Logger service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Cache\MemoryCache\MemoryCacheInterface $memory_cache
   *   Entity memory cache.
   * @param \Drupal\paatokset_policymakers\Service\PolicymakerService $policymaker_service
   *   Meeting service.
   * @param \Drupal\paatokset_datapumppu\Service\Datapumppu $datapumppu
   *   Datapumppu service.
   */
  public function __construct(LoggerChannelFactoryInterface $logger_factory, EntityTypeManagerInterface $entity_type_manager, MemoryCacheInterface $memory_cache, PolicymakerService $policymaker_service, Datapumppu $datapumppu) {
    $this->logger = $logger_factory->get('paatokset_datapumppu');
    $this->nodeStorage = $entity_type_manager->getStorage('node');
    $this->memoryCache = $memory_cache;
    $this->policymakerService = $policymaker_service;
    $this->datapumppu = $datapumppu;
  }

  /**
   * Aggregates statements of all trustees from Datapumppu API.
   *
   * @param array $options
   *   Additional options for the command.
   *
   * @command datapumppu:all-trustee-statements
   *
   * @option year
   *   Get statements from specific year (default is current year).
   * @option since
   *   Get all statements starting from specified year.
   *
   * @usage datapumppu:all-trustee-statements
   *   Retrieves trustee statements from current year.
   * @usage datapumppu:all-trustee-statements --year=2020
   *   Retries trustee statements from a specific year.
   * @usage datapumppu:all-trustee-statements --year=2020 --since
   *    Retries trustee statements starting from year 2020
   *
   * @aliases dp:all-statements
   */
  public function getAllTrusteeStatements(array $options = [
    'year' => NULL,
    'since' => NULL,
  ]): int {
    $since = !empty($options['since']);
    $currentYear = (int) date("Y");

    if (is_numeric($options['year'])) {
      $startYear = (int) $options['year'];
    }
    else {
      $startYear = $currentYear;
    }

    // Limit maximum years to prevent accidentally hitting the API too much.
    if ($currentYear - $startYear < 0 || $currentYear - $startYear > 10) {
      $this->logger->warning("Trying to import too many years");
      return self::EXIT_FAILURE;
    }

    $nids = $this->nodeStorage
      ->getQuery()
      ->condition('type', 'trustee')
      ->condition('status', 1)
      ->execute();

    $langcodes = ['fi', 'sv'];

    // Iterate all trustee nodes.
    foreach ($nids as $nid) {
      /** @var \Drupal\node\NodeInterface $trustee */
      $trustee = $this->nodeStorage->load($nid);

      // Iterate years from $startYear up to $currentYear.
      $year = $startYear;
      do {
        // Iterate all langcodes.
        foreach ($langcodes as $lang) {
          $result = $this->datapumppu->aggregateStatements($trustee, (string) $year, $lang);

          if ($result !== MigrationInterface::RESULT_COMPLETED) {
            return self::EXIT_FAILURE;
          }
        }
      } while ($since && ++$year <= $currentYear);

      // Avoid hitting memory limits.
      $this->memoryCache->deleteAll();
    }

    return self::EXIT_SUCCESS;
  }

  /**
   * Get statements from latest city council meeting.
   *
   * This command is intended to be run automatically after each city council
   * meeting. The command fetches the composition of the latest city council
   * meeting and aggregates statements only from those trustees. This should
   * reduce the amount of queries to datapumppu API.
   *
   * @command datapumppu:latest-statements
   *
   * @usage datapumppu:latest-statements
   *   Retrieves trustee statements from current year.
   *
   * @aliases dp:latest-statements
   */
  public function getLatestTrusteeStatements(): int {
    $langcodes = ['fi', 'sv'];
    $currentYear = date("Y");

    $trustees = $this->policymakerService->getTrustees(PolicymakerService::CITY_COUNCIL_DM_ID);

    foreach ($trustees as $trustee) {
      foreach ($langcodes as $lang) {
        $result = $this->datapumppu->aggregateStatements($trustee, $currentYear, $lang);

        if ($result !== MigrationInterface::RESULT_COMPLETED) {
          return self::EXIT_FAILURE;
        }
      }
    }

    return self::EXIT_SUCCESS;
  }

  /**
   * Import statements of trustee from Datapumppu API.
   *
   * @param string $trusteeId
   *   Get results by specific trustee ID.
   * @param string $lang
   *   Get titles translated in specific language (fi, sv).
   * @param array $options
   *   Additional options for the command.
   *
   * @command datapumppu:get-trustee-statements
   *
   * @option year
   *   Get statements from specific year (default is current year).
   *
   * @usage datapumppu:get-trustee-statements akuankka fi
   *   Retrieves trustee statements from current year.
   * @usage datapumppu:get-trustee-statements akuankka sv --year=2020
   *   Retries trustee statements from a specific year.
   *
   * @aliases dp:statements
   */
  public function getTrusteeStatements(string $trusteeId, string $lang, array $options = [
    'year' => NULL,
  ]): int {

    if (is_numeric($options['year'])) {
      $year = $options['year'];
    }
    else {
      $year = date("Y");
    }

    $trustee = $this->getTrusteeNode($trusteeId);
    if (empty($trustee)) {
      $this->logger->warning("Trustee with id $trusteeId not found");
      return self::EXIT_FAILURE;
    }

    $result = $this->datapumppu->aggregateStatements($trustee, $year, $lang);
    if ($result === MigrationInterface::RESULT_COMPLETED) {
      return self::EXIT_SUCCESS;
    }

    return self::EXIT_FAILURE;
  }

  /**
   * Get trustee entity.
   */
  private function getTrusteeNode(string $trusteeId): ?NodeInterface {
    /** @var \Drupal\node\NodeInterface[] $node */
    $nodes = $this->nodeStorage->loadByProperties([
      'type' => 'trustee',
      'field_trustee_id' => $trusteeId,
    ]);

    if (($node = reset($nodes)) !== FALSE) {
      return $node;
    }

    return NULL;
  }

}
