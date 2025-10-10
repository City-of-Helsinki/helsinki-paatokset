<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\AhjoProxy\DTO;

/**
 * Ahjo chairmanship DTO.
 */
final readonly class Chairmanship {

  public function __construct(
    public string $position,
    public string $organizationId,
    public string $organizationName,
  ) {}

}
