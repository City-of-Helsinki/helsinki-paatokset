<?php

declare(strict_types=1);

namespace Drupal\paatokset\Lupapiste\DTO;

/**
 * A collection DTO to store Lupapiste RSS items.
 */
final readonly class Collection {

  public function __construct(
    public int $total,
    public array $items,
  ) {
  }

}
