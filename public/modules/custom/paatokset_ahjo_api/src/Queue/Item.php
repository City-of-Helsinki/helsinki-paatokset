<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Queue;

use Drupal\paatokset_ahjo_api\Drush\Commands\DTO\MigrateSettings;

/**
 * Ahjo queue item DTO.
 */
final readonly class Item {

  public function __construct(
    public string $id,
    public string $migration,
    // @todo figure out what updatetype does.
    public string $updateType = 'AddedFromDrush',
    public \DateTimeImmutable $created = new \DateTimeImmutable(),
  ) {
  }

  /**
   * Convert to MigrateSettings.
   */
  public function toMigrateSettings(): MigrateSettings {
    return new MigrateSettings(idlist: [$this->id]);
  }

}
