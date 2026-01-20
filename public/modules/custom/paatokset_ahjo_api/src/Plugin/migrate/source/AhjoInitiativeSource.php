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

      yield from array_map(static fn ($initiative) => [
        'Title' => $initiative->title,
        'Date' => $initiative->date->getTimestamp(),
        'FileURI' => $initiative->url,
        'Trustee' => $ahjoData->id,
        'Trustee_NID' => $trustee->id(),
      ], $ahjoData->initiatives);
    }
  }

}
