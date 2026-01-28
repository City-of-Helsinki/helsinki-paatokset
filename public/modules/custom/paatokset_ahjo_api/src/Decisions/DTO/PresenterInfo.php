<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Decisions\DTO;

/**
 * Presenter information with title and name.
 */
final readonly class PresenterInfo {

  public function __construct(
    public ?string $title,
    public ?string $name,
  ) {
  }

}
