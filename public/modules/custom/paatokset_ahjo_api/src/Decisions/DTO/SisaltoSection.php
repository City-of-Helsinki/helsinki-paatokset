<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Decisions\DTO;

/**
 * Sisalto section with heading and HTML content.
 */
final readonly class SisaltoSection {

  public function __construct(
    public ?string $heading,
    public string $content,
  ) {
  }

}
