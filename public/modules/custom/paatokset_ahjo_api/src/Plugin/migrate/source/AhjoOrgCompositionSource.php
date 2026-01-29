<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\migrate\source;

use Drupal\migrate\Attribute\MigrateSource;
use Drupal\paatokset_ahjo_api\AhjoProxy\AhjoProxyException;
use Drupal\paatokset_ahjo_api\Entity\OrganizationType;

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
 * Example, specific organisations:
 *
 * @code
 *  source:
 *    plugin: ahjo_org_composition
 *    idlist:                       # List of specific org IDs to fetch
 *      - '00400'
 *      - '02900'
 * @endcode
 */
#[MigrateSource(id: 'ahjo_org_composition')]
final class AhjoOrgCompositionSource extends AhjoSourceBase {

  /**
   * {@inheritDoc}
   */
  public function fields(): array {
    return [
      'id' => 'Organization ID',
      'nid' => 'Node ID',
      'composition' => 'Organization composition (JSON string)',
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
    if (!empty($this->configuration['idlist'])) {
      $ids = $this->queryOrgIds(orgIds: $this->configuration['idlist']);
    }
    else {
      $ids = $this->queryOrgIds(activeOnly: $this->configuration['orgs'] === 'active');
    }

    if (empty($ids)) {
      $this->logger->warning("No organisations found. Consider running ahjo_decisionmakers migration first.");
      return;
    }

    foreach ($ids as $nid => $id) {
      try {
        // Hard-code language to fi.
        // The composition field is not translatable.
        $decisionmaker = $this->client->getDecisionmaker('fi', $id);

        $this->logger->info('Importting org composition for @id (@count memebers)', [
          '@id' => $id,
          '@count' => count($decisionmaker->composition),
        ]);

        yield [
          'nid' => $nid,
          'id' => $decisionmaker->organization->info->id,
          'composition' => $decisionmaker->composition,
        ];
      }
      catch (AhjoProxyException $e) {
        $this->logger->warning("Could not fetch composition for @id: @message", [
          '@id' => $id,
          '@message' => $e->getMessage(),
        ]);
      }
    }
  }

  /**
   * Query policymaker organization IDs from the database.
   *
   * Loads nodes in chunks to avoid excessive memory usage.
   *
   * @param bool $activeOnly
   *   If TRUE, only return active organizations.
   * @param string[] $orgIds
   *   If provided, only return these specific organization IDs.
   *
   * @return string[]
   *   Organization IDs (field_policymaker_id values).
   */
  private function queryOrgIds(bool $activeOnly = FALSE, array $orgIds = []): array {
    $query = $this->entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->condition('type', 'policymaker')
      ->condition('status', 1)
      ->condition('field_policymaker_id', '', '<>')
      ->accessCheck(FALSE);

    // Trustee organizations should not have composition.
    $and = $query->andConditionGroup();
    foreach (OrganizationType::TRUSTEE_TYPES as $type) {
      $and->condition('field_organization_type', $type, '<>');
    }
    $query->condition($and);

    if ($activeOnly) {
      $query->condition('field_policymaker_existing', 1);
    }

    if (!empty($orgIds)) {
      $query->condition('field_policymaker_id', $orgIds, 'IN');
    }

    $nids = $query->execute();
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = [];

    foreach (array_chunk($nids, 50) as $chunk) {
      $nodes = $storage->loadMultiple($chunk);

      foreach ($nodes as $node) {
        $ids[$node->id()] = $node->get('field_policymaker_id')->value;
      }

      $storage->resetCache($chunk);
    }

    return $ids;
  }

}
