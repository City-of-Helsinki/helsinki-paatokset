<?php

declare(strict_types=1);

namespace Drupal\paatokset_datapumppu\Plugin\migrate\source;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\helfi_api_base\Plugin\migrate\source\HttpSourcePluginBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\paatokset_ahjo_api\Entity\Trustee;
use Drupal\paatokset_datapumppu\DatapumppuImportOptions;
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
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

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
    ?MigrationInterface $migration = NULL,
  ) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition, $migration);
    $instance->logger = $container->get('logger.channel.paatokset_datapumppu');
    $instance->entityTypeManager = $container->get(EntityTypeManagerInterface::class);
    $instance->policymakerService = $container->get('paatokset_policymakers');
    return $instance;
  }

  /**
   * Get import options.
   */
  private function getImportOptions(): DatapumppuImportOptions {
    $currentYear = (int) date("Y");
    $defaults = [
      'dataset' => 'latest',
      'startYear' => $currentYear,
      'endYear' => $currentYear,
    ];

    $configuration = ($this->configuration['config'] ?? []) + $defaults;

    return new DatapumppuImportOptions(...$configuration);
  }

  /**
   * {@inheritDoc}
   */
  protected function initializeListIterator(): \Iterator {
    $config = $this->getImportOptions();

    // The source cannot be sorted in any meaningful way.
    if ($this->isPartialMigrate()) {
      throw new \InvalidArgumentException("Partial migration is not supported");
    }

    // Latest options should be used for cron job imports. It only
    // considers trustees from the latest meeting and therefore
    // makes a lot less requests that 'all' option. However, it
    // can miss older data if compositions change.
    $dataset = match ($config->dataset) {
      'latest' => $this->getLatestTrusteesIterator(),
      'all' => $this->getAllTrusteesIterator(),
    };

    if (!$dataset->valid()) {
      $this->logger->warning("No trustees found. Consider importing trustees and meeting data.");
      return;
    }

    /** @var \Drupal\paatokset_ahjo_api\Entity\Trustee $trustee */
    foreach ($dataset as $trustee) {
      // Optionally filter with datapumppu name:
      if ($config->trustee && $config->trustee !== $trustee->getDatapumppuName()) {
        continue;
      }

      // Iterate years from startYear up to endYear. If startYear == endYear,
      // this loop will execute only once.
      foreach (range($config->startYear, $config->endYear) as $year) {
        $foundResults = FALSE;

        // Iterate all languages supported by Datapumppu.
        foreach (self::LANGCODES as $lang) {
          $results = $this->fetchStatements($trustee, (string) $year, $lang);
          if (!empty($results)) {
            $foundResults = TRUE;
          }

          yield from $results;
        }

        if (!$foundResults) {
          // This warning is useful for debugging trustees whose name in Ahjo
          // differs from their name in Datapumppu. This will also fire for
          // trustees who have not made any statements.
          $this->logger->warning("No results for {$trustee->getDatapumppuName()} in $year");
        }
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
   * Return all trustees.
   *
   * @return \Generator
   *   Iterator of trustee nodes.
   */
  private function getAllTrusteesIterator(): \Generator {
    $nodeStorage = $this->entityTypeManager->getStorage('node');

    // Get all historical data.
    $nids = $nodeStorage
      ->getQuery()
      ->condition('type', 'trustee')
      ->condition('status', 1)
      ->accessCheck(FALSE)
      ->execute();

    foreach ($nids as $nid) {
      /** @var \Drupal\paatokset_ahjo_api\Entity\Trustee $trustee */
      $trustee = $nodeStorage->load($nid);

      yield $trustee;
    }
  }

  /**
   * Return trustees based on latest council meeting.
   *
   * @return \Generator
   *   Iterator of trustee nodes.
   */
  private function getLatestTrusteesIterator(): \Generator {
    yield from $this->policymakerService->getTrustees(PolicymakerService::CITY_COUNCIL_DM_ID);
  }

  /**
   * Get statements from datapumppu.
   *
   * @param \Drupal\paatokset_ahjo_api\Entity\Trustee $trustee
   *   The trustee node.
   * @param string $year
   *   Year to get statements from.
   * @param string $lang
   *   Langcode.
   *
   * @return array
   *   Rows from parsed json response.
   */
  private function fetchStatements(Trustee $trustee, string $year, string $lang): array {
    $url = $this->getStatementsUrl($trustee, $year, $lang);
    $this->logger->info("Fetching from $url");
    $result = $this->getContent($url);

    return array_map(fn (array $row) => array_merge($row, [
      'trustee_nid' => $trustee->id(),
      'langcode' => $lang,
    ]), $result);
  }

  /**
   * Get Datapummpu endpoint.
   *
   * @param \Drupal\paatokset_ahjo_api\Entity\Trustee $trustee
   *   The trustee node.
   * @param string $year
   *   Year to get statements from.
   * @param string $lang
   *   Langcode.
   *
   * @return string
   *   Endpoint url with correct URL parameters.
   */
  private function getStatementsUrl(Trustee $trustee, string $year, string $lang): string {
    $query = http_build_query([
      'name' => $trustee->getDatapumppuName(),
      'year' => $year,
      'lang' => $lang,
    ]);

    return "{$this->configuration['url']}/api/statements?$query";
  }

}
