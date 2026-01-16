<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\migrate\source;

use Drupal\migrate\Attribute\MigrateSource;
use Drupal\paatokset_ahjo_api\AhjoProxy\AhjoProxyException;

/**
 * Source plugin for retrieving cases from Ahjo proxy.
 *
 * Bulk import (date-based search):
 *
 * @code
 *  source:
 *    plugin: ahjo_api_cases
 *    after: '-1 week'    # Start date (defaults to '-1 week')
 *    before: 'now'       # End date (defaults to 'now')
 *    interval: 'P1W'     # Date interval for batching (defaults to 1 week)
 * @endcode
 *
 * Example (ID list mode):
 *
 * @code
 *  source:
 *    plugin: ahjo_api_cases
 *    idlist:             # List of specific case IDs to fetch
 *      - 'HEL 2024-001234'
 *      - 'HEL 2024-005678'
 * @endcode
 */
#[MigrateSource(id: 'ahjo_api_cases')]
final class AhjoCaseSource extends AhjoSourceBase {

  /**
   * {@inheritDoc}
   */
  public function fields(): array {
    return [
      'id' => 'Case ID',
      'caseIdLabel' => 'Case ID Label',
      'title' => 'Case title',
      'created' => 'Case created timestamp (Unix timestamp)',
      'acquired' => 'Case acquired timestamp (Unix timestamp)',
      'classificationCode' => 'Case classification code',
      'classificationTitle' => 'Case classification title',
      'status' => 'Case status',
      'language' => 'Case language',
      'publicityClass' => 'Case publicity class',
      'securityReasons' => 'Case security reasons (array)',
      'handlings' => 'Case handlings (array)',
      'records' => 'Case records (array)',
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function getIds(): array {
    return [
      'id' => [
        'type' => 'string',
      ],
    ];
  }

  /**
   * {@inheritDoc}
   */
  protected function initializeIterator(): \Iterator {
    // If an idlist is provided, fetch only those specific cases.
    // Note: source plugins cannot access --idlist from migrate:import command.
    if (!empty($this->configuration['idlist'])) {
      yield from $this->fetchCasesById($this->configuration['idlist']);
      return;
    }

    // Otherwise, use date-based search.
    try {
      // Search cases that were handled at least a week ago.
      $after = new \DateTimeImmutable($this->configuration['after'] ?? '-1 week');
      $before = new \DateTimeImmutable($this->configuration['before'] ?? 'now');
      $interval = new \DateInterval($this->configuration['interval'] ?? 'P7D');
    }
    catch (\DateMalformedStringException | \DateMalformedIntervalStringException $e) {
      throw new \InvalidArgumentException("Invalid configuration: {$e->getMessage()}", previous: $e);
    }

    // Currently, only Finnish language cases exist.
    // getCases handles interval looping internally.
    $cases = $this->client->getCases('fi', $after, $before, $interval);

    foreach ($cases as $case) {
      try {
        yield from $this->fetchCasesById([$case->id], $case->language);
      }
      catch (AhjoProxyException $e) {
        $this->logger->warning("Failed to fetch case @id: @message", [
          "@id" => $case->id,
          "@message" => $e->getMessage(),
        ]);
        continue;
      }
    }
  }

  /**
   * Fetches cases by their IDs.
   *
   * @param array $ids
   *   Array of case IDs to fetch.
   * @param string $language
   *   Language code (defaults to 'fi').
   *
   * @return \Generator<array>
   *   Generator yielding case data arrays.
   *
   * @throws \Drupal\paatokset_ahjo_api\AhjoProxy\AhjoProxyException
   */
  private function fetchCasesById(array $ids, string $language = 'fi'): \Generator {
    foreach ($ids as $id) {
      $this->logger->info("Fetching case @id", ["@id" => $id]);

      // Fetch the full case data.
      $caseData = $this->client->getCase($language, $id);

      // Cast DTO to array and override complex fields.
      yield array_merge((array) $caseData, [
        'created' => $caseData->created->getTimestamp(),
        'acquired' => $caseData->acquired->getTimestamp(),
        'handlings' => json_encode($caseData->handlings),
        'records' => json_encode($caseData->records),
      ]);
    }
  }

}
