<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\migrate\source;

use Drupal\paatokset_ahjo_api\AhjoProxy\AhjoProxyException;

/**
 * Source plugin for retrieving organization hierarchy from Ahjo.
 *
 * Example:
 *
 * @code
 *  source:
 *    plugin: ahjo_api_organizations
 * @endcode
 *
 * @MigrateSource(
 *   id = "ahjo_api_organizations"
 * )
 */
final class AhjoOrganizationSource extends AhjoSourceBase {

  /**
   * The root id of the whole organization.
   */
  public const string ROOT_ORGANIZATION_ID = '00001';

  /**
   * Organization translations supported by Ahjo.
   */
  private const array ALL_LANGCODES = ['fi', 'sv'];

  /**
   * {@inheritDoc}
   */
  public function fields(): array {
    return [
      'id' => 'Organization ID',
      'name' => 'Organization name',
      'existing' => 'If the organisation is not dissolved',
      'organization_above' => 'ID of the parent organization',
      'type' => 'Organization type ID',
      'langcode' => 'Langcode',
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
      'langcode' => [
        'type' => 'string',
      ],
    ];
  }

  /**
   * {@inheritDoc}
   */
  protected function initializeIterator(): \Iterator {
    // Iterate all known organization IDs and fetch
    // their details and child organizations from Ahjo.
    $ids = $this->getOrganizationIds();
    foreach ($ids as $id) {
      $rows = [];

      // Get org chart for each langcode.
      foreach (self::ALL_LANGCODES as $langcode) {
        $this->logger->info("Processing organization @id (@language)", [
          "@id" => $id,
          "@language" => $langcode,
        ]);

        try {
          $response = $this->client->getOrganization($langcode, $id);
        }
        catch (AhjoProxyException $e) {
          $this->logger->warning("Failed to fetch organization @id (@language): @message", [
            "@id" => $id,
            "@language" => $langcode,
            "@message" => $e->getMessage(),
          ]);
          continue;
        }

        $parent = $response->parent;

        // Root organization is allowed not to have a parent.
        if (!$parent && $response->organization->id !== self::ROOT_ORGANIZATION_ID) {
          // Skip if the organization is dissolved. Example 🐙:
          // https://paatokset.hel.fi/fi/ahjo-proxy/organization/single/01101.
          if (!$response->organization->existing) {
            continue;
          }

          // We assume that the organization has a single parent.
          // Fail loudly if this is not true.
          throw new \LogicException("Organization should have a single parent");
        }

        // Process current organization.
        $rows[] = [
          'id' => $response->organization->id,
          'name' => $response->organization->name,
          'existing' => $response->organization->existing,
          'type' => $response->organization->type->value,
          'langcode' => $langcode,
          'organization_above' => $parent?->id ?? NULL,
        ];

        // Process child organizations.
        foreach ($response->children as $child) {
          // This child is already known and will be processed later separately.
          if (in_array($child->id, $ids)) {
            continue;
          }

          $rows[] = [
            'id' => $child->id,
            'name' => $child->name,
            'existing' => $child->existing,
            'type' => $response->organization->type->value,
            'langcode' => $langcode,
            'organization_above' => $response->organization->id,
          ];
        }
      }

      if ($rows) {
        yield from $rows;
      }
    }
  }

  /**
   * Gets a list of known organization IDs.
   */
  private function getOrganizationIds(): array {
    // Update & check child organizations of all known organizations.
    $storage = $this
      ->entityTypeManager
      ->getStorage('ahjo_organization');

    $ids = $storage
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('sync_attempts', 10, '<=')
      // Update oldest first.
      ->sort('changed', 'ASC')
      ->execute();

    // Add the root organization so the import process can be bootstrapped.
    if (empty($ids)) {
      $ids[] = self::ROOT_ORGANIZATION_ID;
    }

    return $ids;
  }

}
