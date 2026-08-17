<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\migrate\process;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Looks up an existing trustee nid and heals stale migrate map rows.
 *
 * When the migrate map destination id does not match the trustee node found by
 * agent id (field_trustee_id), the map row is deleted so the entity destination
 * updates the correct node instead of inserting a duplicate.
 *
 * Available configuration keys:
 * - source: The Ahjo agent id.
 *
 * Example:
 *
 * @code
 * process:
 *   nid:
 *     plugin: ahjo_trustee_nid
 *     source: agent_id
 * @endcode
 *
 * @see \Drupal\migrate\Plugin\MigrateProcessInterface
 */
#[MigrateProcess('ahjo_trustee_nid')]
final class AhjoTrusteeNid extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string, mixed> $configuration
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    private readonly MigrationInterface $migration,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LoggerInterface $logger,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string, mixed> $configuration
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?MigrationInterface $migration = NULL): static {
    assert($migration instanceof MigrationInterface);
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get(EntityTypeManagerInterface::class),
      $container->get('logger.channel.paatokset_ahjo_api'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property): ?string {
    if ($value === NULL || $value === '') {
      return NULL;
    }

    $agent_id = (string) $value;
    $actual_nid = $this->lookupTrusteeNid($agent_id);

    $id_map = $this->migration->getIdMap();
    $mapped = $id_map->lookupDestinationIds($row->getSourceIdValues());
    if ($mapped) {
      $mapped_nid = (string) reset($mapped)[0];
      if ($mapped_nid !== ($actual_nid ?? '')) {
        $this->logger->notice('Removing stale migrate map entry for trustee @agent_id (mapped nid @mapped, actual nid @actual).', [
          '@agent_id' => $agent_id,
          '@mapped' => $mapped_nid,
          '@actual' => $actual_nid ?? 'NULL',
        ]);
        $id_map->delete($row->getSourceIdValues());
      }
    }

    return $actual_nid;
  }

  /**
   * Looks up an existing trustee node by Ahjo agent id.
   */
  private function lookupTrusteeNid(string $agent_id): ?string {
    $ids = $this->entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'trustee')
      ->condition('field_trustee_id', $agent_id)
      ->range(0, 1)
      ->latestRevision()
      ->execute();

    if ($ids === []) {
      return NULL;
    }

    return (string) array_first($ids);
  }

}
