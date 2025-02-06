<?php

declare(strict_types=1);

namespace Drupal\paatokset_allu\Client;

/**
 * A DTO for Allu settings.
 */
final readonly class Settings {

  /**
   * Constructs a new instance.
   *
   * @param string $username
   *   Allu username.
   * @param string $password
   *   Allu password.
   * @param string $baseUrl
   *   Allu base url.
   */
  public function __construct(
    public string $username,
    public string $password,
    public string $baseUrl,
  ) {
  }

}
