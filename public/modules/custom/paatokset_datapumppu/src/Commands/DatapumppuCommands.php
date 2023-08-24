<?php

declare(strict_types = 1);

namespace Drupal\paatokset_datapumppu\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\node\NodeInterface;
use Drupal\node\NodeStorageInterface;
use Drupal\paatokset_datapumppu\Service\Datapumppu;
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
   * @var \Drupal\node\NodeStorageInterface
   */
  protected NodeStorageInterface $nodeStorage;

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
   * @param \Drupal\paatokset_datapumppu\Service\Datapumppu $datapumppu
   *   Datapumppu service.
   */
  public function __construct(LoggerChannelFactoryInterface $logger_factory, EntityTypeManagerInterface $entity_type_manager, Datapumppu $datapumppu) {
    $this->logger = $logger_factory->get('paatokset_datapumppu');
    $this->nodeStorage = $entity_type_manager->getStorage('node');
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
   *
   * @usage datapumppu:get-trustee-statements
   *   Retrieves trustee statements from current year.
   * @usage datapumppu:get-trustee-statements --year=2020
   *   Retries trustee statements from a specific year.
   *
   * @aliases dp:all-statements
   */
  public function getAllTrusteeStatements(array $options = [
    'year' => NULL,
  ]): int {

    if (is_numeric($options['year'])) {
      $year = $options['year'];
    }
    else {
      $year = date("Y");
    }

    return self::EXIT_SUCCESS;
  }

  /**
   * Import statements of trustee from Datapumppu API.
   *
   * @param string $trusteeId
   *   Get results by specific trustee ID.
   * @param array $options
   *   Additional options for the command.
   *
   * @command datapumppu:get-trustee-statements
   *
   * @option year
   *   Get statements from specific year (default is current year).
   *
   * @usage datapumppu:get-trustee-statements akuankka
   *   Retrieves trustee statements from current year.
   * @usage datapumppu:get-trustee-statements akuankka --year=2020
   *   Retries trustee statements from a specific year.
   *
   * @aliases dp:statements
   */
  public function getTrusteeStatements(string $trusteeId, array $options = [
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

    $this->datapumppu->aggregateStatements($trustee, $year);

    return self::EXIT_SUCCESS;
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
