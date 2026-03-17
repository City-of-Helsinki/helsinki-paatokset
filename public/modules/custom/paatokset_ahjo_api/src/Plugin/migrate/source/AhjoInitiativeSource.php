<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\migrate\source;

use Drupal\migrate\Attribute\MigrateSource;
use Drupal\migrate\MigrateException;
use Drupal\paatokset_ahjo_api\AhjoProxy\AhjoProxyException;
use Drupal\paatokset_ahjo_api\Entity\Trustee;

/**
 * Source plugin for retrieving initiatives from Ahjo proxy.
 *
 * Example:
 *
 * @code
 *  source:
 *    plugin: ahjo_api_initiatives
 * @endcode
 */
#[MigrateSource(id: 'ahjo_api_initiatives')]
final class AhjoInitiativeSource extends AhjoSourceBase {

  // Track used keys to avoid duplicates.
  protected array $trusteeTimestamps = [];
  
  /**
   * {@inheritDoc}
   */
  public function fields(): array {
    return [
      'Title' => 'Initiative title',
      'Date' => 'Initiative timestamp',
      'FileURI' => 'Initiative file URI',
      'Trustee' => 'ID of the trustee responsible for the initiative',
      'Trustee_NID' => 'Node ID of the trustee responsible for the initiative',
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function getIds(): array {
    return [
      'Trustee' => ['type' => 'string'],
      'Date' => ['type' => 'string'],
      'Index' => ['type' => 'integer'],
    ];
  }

  /**
   * {@inheritDoc}
   */
  protected function initializeIterator(): \Iterator {
    $storage = $this->entityTypeManager->getStorage('node');

    $ids = $storage
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'trustee')
      ->execute();

    foreach ($ids as $id) {
      $trustee = $storage->load($id);
      assert($trustee instanceof Trustee);

      // Initiatives are not translated, so the language should not matter.
      try {
        $ahjoData = $this->client->getTrustee($trustee->language()->getId(), $trustee->getAhjoId());
      }
      catch (AhjoProxyException $e) {
        throw new MigrateException($e->getMessage(), previous: $e);
      }

      if (count($ahjoData->initiatives) > 0) {
        $this->logger->info("Found @count initiatives for @trustee", [
          '@count' => count($ahjoData->initiatives),
          '@trustee' => $ahjoData->id,
        ]);
      }

      yield from array_map(fn ($initiative) => [
        'Title' => $initiative->title,
        'Date' => $initiative->date->getTimestamp(),
        'FileURI' => $initiative->url,
        'Trustee' => $ahjoData->id,
        'Trustee_NID' => $trustee->id(),
        'Index' => $this->getIndex($ahjoData->id, $initiative->date->getTimestamp()),
      ], $ahjoData->initiatives);
    }
  }

  /**
   * Get a index for the initiative.
   * 
   * This is usually 0, but if there are multiple initiatives for a single
   * trustee with identical timestamps, it will be incremented to make sure
   * we have a unique id field combination for all.
   *
   * @param string $trusteeId
   *   The ID of the trustee.
   * @param int $timestamp
   *   The timestamp of the initiative.
   *
   * @return int
   *   The index for the initiative.
   */
  protected function getIndex(string $trusteeId, int $timestamp): int {
    $key = "{$trusteeId}:{$timestamp}";
    if (isset($this->trusteeTimestamps[$key])) {
      $this->trusteeTimestamps[$key]++;
    }
    else {
      $this->trusteeTimestamps[$key] = 0;
    }
    return $this->trusteeTimestamps[$key];
  }
}
