<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\migrate\source;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateException;
use Drupal\migrate\Plugin\MigrationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
final class AhjoOrganizationSource extends AhjoSourceBase implements ContainerFactoryPluginInterface {
  /**
   * The root id of the whole organization.
   */
  public const ROOT_ORGANIZATION_ID = '00001';

  /**
   * Organization translations supported by Ahjo.
   */
  private const ALL_LANGCODES = ['fi', 'sv'];

  /**
   * The entity type manager.
   */
  private EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ?MigrationInterface $migration = NULL,
  ): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition, $migration);
    $instance->entityTypeManager = $container->get(EntityTypeManagerInterface::class);
    return $instance;
  }

  /**
   * {@inheritDoc}
   */
  public function fields(): array {
    return [
      'id' => 'Organization ID',
      'name' => 'Organization name',
      'existing' => 'If the organisation is not dissolved',
      'organization_above' => 'ID of the parent organization',
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
    if (!$this->ahjoProxy->isOperational()) {
      $this->logger->error('Ahjo Proxy is not operational, exiting.');
      throw new MigrateException('Ahjo Proxy is not operational, exiting.');
    }

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

        $response = $this->getOrganization($id, $langcode);
        if (!$response) {
          $this->logger->warning("Failed to fetch organization @id (@language)", [
            "@id" => $id,
            "@language" => $langcode,
          ]);

          continue;
        }

        $parents = $response['OrganizationLevelAbove']['organizations'] ?? [];

        // Root organization is allowed not to have a parent.
        if (count($parents) !== 1 && $response['ID'] !== self::ROOT_ORGANIZATION_ID) {
          // Skip if the organization is dissolved. Example 🐙:
          // https://paatokset.hel.fi/fi/ahjo-proxy/organization/single/01101.
          if ($this->isDissolved($response)) {
            continue;
          }

          // We assume that organization has a single parent.
          // Fail loudly if this is not true.
          throw new \LogicException("Organization should have a single parent");
        }

        $parent = $parents[0] ?? NULL;

        // Process current organization.
        $rows[] = $this->formatOrganization(
          $response,
          $parent,
          $langcode
        );

        // Process child organizations.
        foreach ($response['OrganizationLevelBelow']['organizations'] ?? [] as $child) {
          // This child is already known and will be processed later separately.
          if (in_array($child['ID'], $ids)) {
            continue;
          }

          $rows[] = $this->formatOrganization($child, $response, $langcode);
        }
      }

      if ($rows) {
        yield from $rows;
      }
    }
  }

  /**
   * Formats Ahjo organization response to plugin format.
   */
  private function formatOrganization(array $current, ?array $parent, string $langcode): array {
    $title = Unicode::truncate($current['Name'], '255', TRUE, TRUE);
    if (empty($title)) {
      $title = $current['ID'];
    }

    return [
      'id' => $current['ID'],
      'name' => $title,
      'langcode' => $langcode,
      'existing' => $this->isDissolved($current),
      'organization_above' => $parent['ID'] ?? NULL,
    ];
  }

  /**
   * Gets list of known organization IDs.
   */
  private function getOrganizationIds(): array {
    // Update & check child organizations of all known organizations.
    $storage = $this
      ->entityTypeManager
      ->getStorage('ahjo_organization');

    $ids = $storage
      ->getQuery()
      ->accessCheck(FALSE)
      // Update oldest first.
      ->sort('changed', 'ASC')
      ->execute();

    // Add the root organization so the import process can be bootstrapped.
    if (empty($ids)) {
      $ids[] = self::ROOT_ORGANIZATION_ID;
    }

    return $ids;
  }

  /**
   * Returns true if given organization is dissolved.
   *
   * @param array $organization
   *   Organization array from Ahjo.
   */
  private function isDissolved(array $organization): bool {
    return $organization['Existing'] !== 'true' && $organization['Existing'] !== TRUE;
  }

  /**
   * Get organization info from Ahjo.
   *
   * @return ?array
   *   NULL if received invalid data from Ahjo.
   */
  private function getOrganization(string $id, string $langcode): ?array {
    if (!empty(getenv('AHJO_PROXY_BASE_URL'))) {
      $url = 'organization/single/' . $id . '?apireqlang=' . $langcode;
      $query_string = NULL;
    }
    else {
      $url = 'organization';
      $query_string = 'orgid=' . $id . '&apireqlang=' . $langcode;
    }

    $organization = $this->ahjoProxy->getData($url, $query_string);

    // Ahjo proxy responses are formatted a bit differently.
    if (!empty($organization['decisionMakers'][0]['Organization'])) {
      $organization = $organization['decisionMakers'][0]['Organization'];
    }

    if (empty($organization['ID'])) {
      return NULL;
    }

    return $organization;
  }

}
