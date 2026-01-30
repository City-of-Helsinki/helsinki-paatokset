<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\migrate\source;

use Drupal\migrate\Attribute\MigrateSource;
use Drupal\paatokset_ahjo_api\AhjoProxy\AhjoProxyException;
use Drupal\paatokset_ahjo_api\AhjoProxy\DTO\Decisionmaker;

/**
 * Source plugin for retrieving decisionmakers from Ahjo proxy.
 *
 * Bulk import (date-based search):
 *
 * @code
 *  source:
 *    plugin: ahjo_api_decisionmakers
 *    after: '-1 week'       # Start dale (defaults to '-3 months')
 *    before: 'now'          # End date (defaults to 'now')
 *    interval: 'P1W'        # Date interval for batching (defaults to 1 month)
 * @endcode
 *
 * Example (ID list mode):
 *
 * @code
 *  source:
 *    plugin: ahjo_api_decisionmakers
 *    idlist:             # List of specific case IDs to fetch
 *      - '00400'
 *      - '02900'
 * @endcode
 */
#[MigrateSource(id: 'ahjo_api_decisionmakers')]
final class AhjoDecisionmakerSource extends OrganizationSourceBase {

  /**
   * {@inheritDoc}
   */
  public function fields(): array {
    return parent::fields() + [
      'formed' => 'Organization formed timestamp (Unix timestamp)',
      'dissolved' => 'Organization dissolved timestamp (Unix timestamp)',
      'type_label' => 'Organization type label',
      'is_decisionmaker' => 'Whether the organization is a decisionmaker',
      'sector' => 'Organization sector (array)',
      'parent_name' => 'Parent organization name',
      'composition' => 'Organization composition (JSON string)',
    ];
  }

  /**
   * {@inheritDoc}
   */
  protected function initializeIterator(): \Iterator {
    foreach (self::ALL_LANGCODES as $langcode) {
      // If an idlist is provided, fetch only those specific cases.
      // Note: source plugins can't access --idlist from migrate:import command.
      if (!empty($this->configuration['idlist'])) {
        yield from $this->fetchDecisionmakersById($this->configuration['idlist'], $langcode);
        return;
      }

      // Otherwise, use date-based search.
      try {
        // Search cases that were handled at least a week ago.
        $after = new \DateTimeImmutable($this->configuration['after'] ?? '-6 month');
        $before = new \DateTimeImmutable($this->configuration['before'] ?? 'now');
        $interval = new \DateInterval($this->configuration['interval'] ?? 'P1M');
      }
      catch (\DateMalformedStringException | \DateMalformedIntervalStringException $e) {
        throw new \InvalidArgumentException("Invalid configuration: {$e->getMessage()}", previous: $e);
      }

      try {
        $decisionmakers = $this->client->getDecisionmakers($langcode, $after, $before, $interval);
      }
      catch (AhjoProxyException $e) {
        $this->logger->warning("Failed to fetch decisionmaker: @message", [
          "@message" => $e->getMessage(),
        ]);

        continue;
      }

      foreach ($decisionmakers as $decisionmaker) {
        $this->logger->info("Importing @id (@language)", [
          "@id" => $decisionmaker->organization->info->id,
          "@language" => $langcode,
        ]);

        yield $this->flattenDecisionmaker($decisionmaker);
      }
    }
  }

  /**
   * Fetches decisionmakers by their IDs.
   *
   * @param array $ids
   *   Array of case IDs to fetch.
   * @param string $language
   *   Language code.
   *
   * @return \Generator<array>
   *   Generator yielding decisionmaker data arrays.
   *
   * @throws \Drupal\paatokset_ahjo_api\AhjoProxy\AhjoProxyException
   */
  private function fetchDecisionmakersById(array $ids, string $language): \Generator {
    foreach ($ids as $id) {
      $this->logger->info("Fetching decisionmaker @id", ["@id" => $id]);

      // Fetch the full case data.
      $decisionmakerData = new Decisionmaker(
        $this->client->getOrganization($language, $id),
        // Fetching decisionmakers one by one is finicky. The decisionmakers
        // endpoint doesn't return full organization data, and the organization
        // endpoint does not return composition. The composition is migrated
        // separately with ahjo_org_composition migration, so we ignore the
        // composition here and only return the organization endpoint data.
        [],
        $language,
      );

      yield $this->flattenDecisionmaker($decisionmakerData);
    }
  }

  /**
   * Flatten decisionmaker for migration.
   */
  private function flattenDecisionmaker(Decisionmaker $decisionmaker): array {
    $org = $decisionmaker->organization;
    $info = $org->info;

    return [
      'id' => $info->id,
      'name' => $info->name,
      'existing' => $info->existing,
      'formed' => $info->formed->getTimestamp(),
      'dissolved' => $info->dissolved->getTimestamp(),
      'type' => $info->type->value,
      'type_label' => $info->typeLabel,
      'is_decisionmaker' => $info->isDecisionMaker,
      'sector' => $org->sector,
      'parent_name' => $org->parent?->name,
      'organization_above' => $org->parent?->id,
      'composition' => json_encode($decisionmaker->composition),
      'langcode' => $decisionmaker->langcode,
    ];
  }

}
