<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\AhjoProxy\DTO;

/**
 * Response from the decisionmaker endpoint.
 *
 * Decisionmakers are organizations that can have composition.
 * As far as I understand, not all organizations are decisionmakers,
 * but all decisionmakers are organizations.
 */
final readonly class Decisionmaker {

  public function __construct(
    public Organization $organization,
    public array $composition,
    public string $langcode,
  ) {
  }

}
