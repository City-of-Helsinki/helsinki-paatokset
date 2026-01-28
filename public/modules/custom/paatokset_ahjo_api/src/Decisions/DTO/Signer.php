<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Decisions\DTO;

/**
 * Individual signer information.
 */
final readonly class Signer {

  public function __construct(
    public string $name,
    public string $title,
  ) {
  }

}
