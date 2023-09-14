<?php

declare(strict_types=1);

namespace Drupal\paatokset_datapumppu\Plugin\migrate\source;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\helfi_api_base\Plugin\migrate\source\HttpSourcePluginBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\node\NodeInterface;
use Drupal\paatokset_policymakers\Service\PolicymakerService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Source plugin for retrieving data from Datapumppu API.
 *
 * Example (All statements):
 *
 * @code
 *  source:
 *    plugin: datapumppu_statements
 *    start_year: 2017
 *    trustees: all
 * @endcode
 *
 * Example (Statements from latest meeting):
 *
 * @code
 *   source:
 *     plugin: datapumppu_statements
 *     trustees: latest
 * @endcode
 *
 * @MigrateSource(
 *   id = "datapumppu_statements"
 * )
 */
final class DatapumppuStatementsSource extends HttpSourcePluginBase implements ContainerFactoryPluginInterface {
  /**
   * Start year of historical data in datapumppu.
   */
  public const DATAPUMPPU_FIRST_YEAR = 2017;

  /**
   * Languages supported by datapumppu.
   *
   * @var string[]
   */
  private const LANGCODES = ['fi', 'sv'];

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
   * Meeting service.
   *
   * @var \Drupal\paatokset_policymakers\Service\PolicymakerService
   */
  protected PolicymakerService $policymakerService;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
    MigrationInterface $migration = NULL
  ) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition, $migration);
    $instance->logger = $container->get('logger.factory')->get('paatokset_datapumppu');
    $instance->nodeStorage = $container->get('entity_type.manager')->getStorage('node');
    $instance->policymakerService = $container->get('paatokset_policymakers');
    return $instance;
  }

  /**
   * {@inheritDoc}
   */
  protected function initializeListIterator(): \Iterator {
    $currentYear = (int) date("Y");

    if (is_numeric($this->configuration['start_year'] ?? NULL)) {
      $startYear = (int) $this->configuration['start_year'];
    }
    else {
      $startYear = $currentYear;
    }

    // Limit maximum years to prevent accidentally hitting the API too much.
    if ($startYear > $currentYear || $startYear < self::DATAPUMPPU_FIRST_YEAR) {
      throw new \InvalidArgumentException("Invalid start year");
    }

    // The source cannot be sorted in any meaningful way.
    if ($this->isPartialMigrate()) {
      throw new \InvalidArgumentException("Partial migration is not supported");
    }

    $trustees = match ($this->configuration['trustees']) {
      'latest' => $this->getLatestTrusteesIterator(),
      'all' => $this->getAllTrusteesIterator(),
    };

    foreach ($trustees as $trustee) {
      $foundResults = FALSE;

      // Iterate years from $startYear up to $currentYear. If start_year
      // configuration is not provided, this loop will execute only once for
      // current year.
      foreach (range($startYear, $currentYear) as $year) {
        // Iterate all languages supported by Datapumppu.
        foreach (self::LANGCODES as $lang) {
          $results = $this->fetchStatements($trustee, (string) $year, $lang);
          if (!empty($results)) {
            $foundResults = TRUE;
          }

          yield from $results;
        }
      }

      if (!$foundResults) {
        // This warning is useful for debugging trustees whose name in Ahjo
        // differs from their name in Datapumppu. This will also fire for
        // trustees who have not made any statements.
        $this->logger->warning("No results for {$this->formatTrusteeName($trustee)}");
      }
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
      'startTime' => [
        'type' => 'string',
      ],
      'langcode' => [
        'type' => 'string',
      ],
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function __toString(): string {
    return 'DatapumppuStatements';
  }

  /**
   * Return list of all trustees.
   *
   * @return \Iterator|NodeInterface[]
   *   Iterator of trustee nodes.
   */
  private function getAllTrusteesIterator(): \Iterator {
    // Get all historical data.
    $nids = $this->nodeStorage
      ->getQuery()
      ->condition('type', 'trustee')
      ->condition('status', 1)
      ->accessCheck(FALSE)
      ->execute();

    foreach ($nids as $nid) {
      /** @var \Drupal\node\NodeInterface $trustee */
      $trustee = $this->nodeStorage->load($nid);

      yield $trustee;
    }
  }

  /**
   * Return list of trustees based on latest council meeting.
   *
   * @return \Iterator|NodeInterface[]
   *   Iterator of trustee nodes.
   */
  private function getLatestTrusteesIterator(): array {
    return $this->policymakerService->getTrustees(PolicymakerService::CITY_COUNCIL_DM_ID);
  }

  /**
   * Get statements from datapumppu.
   *
   * @param \Drupal\node\NodeInterface $trustee
   *   The trustee node.
   * @param string $year
   *   Year to get statements from.
   * @param string $lang
   *   Langcode.
   *
   * @return array
   *   Rows from parsed json response.
   */
  private function fetchStatements(NodeInterface $trustee, string $year, string $lang): array {
    $url = $this->getStatementsUrl($trustee, $year, $lang);
    $this->logger->info("Fetching from $url");
    $result = $this->getContent($url);

    return array_map(fn ($row) => array_merge($row, [
      'trustee_nid' => $trustee->id(),
      'langcode' => $lang,
    ]), $result);
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
  private function getStatementsUrl(NodeInterface $trustee, string $year, string $lang): string {
    $query = http_build_query([
      'name' => self::formatTrusteeName($trustee),
      'year' => $year,
      'lang' => $lang,
    ]);

    return "{$this->configuration['url']}/api/statements?$query";
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
    if (!$trustee->get('field_trustee_datapumppu_id')->isEmpty()) {
      return $trustee->get('field_trustee_datapumppu_id')->getString();
    }

    return str_replace(',', '', $trustee->getTitle());
  }

}
