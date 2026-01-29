<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\migrate\source;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\helfi_api_base\Plugin\migrate\source\HttpSourcePluginBase;
use Drupal\migrate\Attribute\MigrateSource;
use Drupal\migrate\MigrateException;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\node\NodeInterface;
use Drupal\paatokset_ahjo_api\Entity\OrganizationType;
use Drupal\paatokset_ahjo_proxy\AhjoProxy;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Source plugin for retrieving organization compositions.
 *
 * Example, all org compositions:
 *
 * @code
 *  source:
 *    plugin: ahjo_org_composition
 *    orgs: all
 * @endcode
 *
 * Example, only active organizations:
 *
 * @code
 *  source:
 *    plugin: ahjo_org_composition
 *    orgs: active
 * @endcode
 *
 * Example, single organisation:
 *
 * @code
 *  source:
 *    plugin: ahjo_org_composition
 *    orgs: ids
 *    org_id: 00400,02900
 * @endcode
 */
#[MigrateSource(id: 'ahjo_org_composition')]
final class AhjoOrgCompositionSource extends HttpSourcePluginBase implements ContainerFactoryPluginInterface {
  /**
   * {@inheritdoc}
   */
  protected bool $useRequestCache = FALSE;

  /**
   * {@inheritdoc}
   */
  protected $skipCount = TRUE;

  /**
   * {@inheritDoc}
   */
  protected $trackChanges = FALSE;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * Node storage service.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $nodeStorage;

  /**
   * Ahjo proxy service.
   *
   * @var \Drupal\paatokset_ahjo_proxy\AhjoProxy
   */
  protected AhjoProxy $ahjoProxy;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ?MigrationInterface $migration = NULL,
  ) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition, $migration);
    $instance->logger = $container->get('logger.factory')->get('paatokset_ahjo_api');
    $instance->nodeStorage = $container->get('entity_type.manager')->getStorage('node');
    $instance->ahjoProxy = $container->get('paatokset_ahjo_proxy');
    return $instance;
  }

  /**
   * {@inheritDoc}
   */
  protected function initializeListIterator(): \Iterator {
    // The source cannot be sorted in any meaningful way.
    if ($this->isPartialMigrate()) {
      throw new \InvalidArgumentException("Partial migration is not supported");
    }

    if ($this->configuration['orgs'] === 'ids' && empty($this->configuration['org_ids'])) {
      throw new \InvalidArgumentException("Trying to run migration for single organization without org_ids parameter.");
    }

    $orgs = match ($this->configuration['orgs']) {
      'all' => $this->getAllOrgsIterator(),
      'active' => $this->getActiveOrgsIterator(),
      'ids' => $this->getIdOrgIterator($this->configuration['org_ids']),
    };

    if (!$orgs->valid()) {
      $this->logger->warning("No organisations found. Consider running ahjo_decisionmakers migration first.");
      return;
    }

    foreach ($orgs as $org) {
      $results = $this->fetchOrgData($org);
      if (empty($results)) {
        $this->logger->warning("Could not fetch data for {$org->get('field_policymaker_id')->value}");
      }

      yield from $results;
    }
  }

  /**
   * {@inheritDoc}
   */
  public function fields(): array {
    return [];
  }

  /**
   * {@inheritDoc}
   */
  public function getIds(): array {
    return [
      'ID' => [
        'type' => 'string',
      ],
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function __toString(): string {
    return 'AhjoOrgCompositions';
  }

  /**
   * Get default entity query for fetching decisionmaker nodes.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   Entity query that filters out unpublished and non-decisionmaker nodes.
   */
  private function getDefaultQuery(): QueryInterface {
    $query = $this->nodeStorage
      ->getQuery()
      ->condition('type', 'policymaker')
      ->condition('status', 1)
      ->condition('field_policymaker_id', '', '<>')
      ->accessCheck(FALSE);

    $and = $query->andConditionGroup();
    foreach (OrganizationType::TRUSTEE_TYPES as $type) {
      $and->condition('field_organization_type', $type, '<>');
    }
    $query->condition($and);

    return $query;
  }

  /**
   * Return all decisionmaker organizations.
   *
   * @return \Generator
   *   Iterator of policymaker nodes.
   */
  private function getAllOrgsIterator(): \Generator {
    $query = $this->getDefaultQuery();
    $nids = $query->execute();
    foreach ($nids as $nid) {
      /** @var \Drupal\node\NodeInterface $org */
      $org = $this->nodeStorage->load($nid);
      yield $org;
    }
  }

  /**
   * Return only active decisionmaker organizations.
   *
   * @return \Generator
   *   Iterator of policymaker nodes.
   */
  private function getActiveOrgsIterator(): \Generator {
    $query = $this->getDefaultQuery();
    $query->condition('field_policymaker_existing', 1);
    $nids = $query->execute();
    foreach ($nids as $nid) {
      /** @var \Drupal\node\NodeInterface $org */
      $org = $this->nodeStorage->load($nid);
      yield $org;
    }
  }

  /**
   * Return decisionmaker organisations by ID.
   *
   * @param string $ids
   *   IDs separated by comma.
   *
   * @return \Generator
   *   Iterator of policymaker nodes.
   */
  private function getIdOrgIterator(string $ids): \Generator {
    $query = $this->getDefaultQuery();
    $query->condition('field_policymaker_id', explode(',', $ids), 'IN');
    $nids = $query->execute();
    foreach ($nids as $nid) {
      /** @var \Drupal\node\NodeInterface $org */
      $org = $this->nodeStorage->load($nid);
      yield $org;
    }
  }

  /**
   * Get organization data from AHJO API.
   *
   * @param \Drupal\node\NodeInterface $org
   *   The policymaker node.
   *
   * @return array
   *   Rows from parsed JSON response.
   */
  private function fetchOrgData(NodeInterface $org): array {
    if (!$this->ahjoProxy->isOperational()) {
      $this->logger->error('Ahjo Proxy is not operational, exiting.');
      throw new MigrateException('Ahjo Proxy is not operational, exiting.');
    }

    $id = strtoupper($org->get('field_policymaker_id')->value);

    // Determine if data should be fetched from proxy or AHJO.
    if (!empty(getenv('AHJO_PROXY_BASE_URL'))) {
      $url = 'decisionmaker/single/' . (string) $id;
      $query_string = 'apireqlang=fi';
    }
    else {
      $url = 'organization/decisionmakingorganizations';
      $query_string = 'orgid=' . (string) $id . '&apireqlang=fi';
    }

    $organization = $this->ahjoProxy->getData($url, $query_string);

    // Normalize data because API returns this in an array.
    if (!empty($organization['organizations'])) {
      $organization = $organization['organizations'];
    }

    if (empty($organization[0]['ID'])) {
      $this->logger->error('Data not found for @id', [
        '@id' => $id,
      ]);
      return [];
    }

    return $organization;
  }

}
