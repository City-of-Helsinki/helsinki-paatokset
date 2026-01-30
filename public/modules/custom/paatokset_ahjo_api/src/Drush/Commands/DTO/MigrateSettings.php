<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Drush\Commands\DTO;

/**
 * DTO for migration settings.
 */
final readonly class MigrateSettings {

  /**
   * Constructor.
   *
   * @param string|null $after
   *   Import items modified after this date.
   * @param string|null $before
   *   Import items modified before this date.
   * @param string|null $interval
   *   Date interval for batching requests.
   * @param string[]|null $idlist
   *   Import only these items.
   * @param bool $update
   *   Force update of existing items.
   * @param bool $queue
   *   Add new cases to the aggregation queue.
   */
  public function __construct(
    public string|null $after = NULL,
    public string|null $before = NULL,
    public string|null $interval = NULL,
    public array|null $idlist = NULL,
    public bool $update = FALSE,
    public bool $queue = FALSE,
  ) {
    // Using `prepareUpdate()` modifies the idmap for all items. This
    // forces all items to be updated, which is expensive when there
    // is a lot of historical data.
    if ($update && empty($idlist) && !$queue) {
      throw new \InvalidArgumentException('Cannot update without idlist or queue.');
    }
  }

  /**
   * Creates MigrateSettings from Drush options.
   *
   * @param array $options
   *   Drush command options.
   *
   * @return self
   *   The MigrateSettings instance.
   *
   * @throws \InvalidArgumentException
   *   If date strings are malformed or validation fails.
   */
  public static function fromOptions(array $options): self {
    // If an idlist is provided, use ID list mode.
    if (!empty($options['idlist'])) {
      $ids = array_map('trim', explode(',', $options['idlist']));
      return new self(
        idlist: $ids,
        update: (bool) $options['update'],
        queue: (bool) $options['queue'],
      );
    }

    // Otherwise, use date-based search.
    return new self(
      after: $options['after'] ?? NULL,
      before: $options['before'] ?? NULL,
      interval: $options['interval'] ?? NULL,
      update: (bool) $options['update'],
      queue: (bool) $options['queue'] ?? FALSE,
    );
  }

  /**
   * Converts settings to migration source configuration.
   *
   * @return array
   *   Migration source configuration array.
   */
  public function toSourceConfiguration(): array {
    if ($this->idlist !== NULL) {
      return ['idlist' => $this->idlist];
    }

    return [
      'after' => $this->after,
      'before' => $this->before,
      'interval' => $this->interval,
    ];
  }

}
