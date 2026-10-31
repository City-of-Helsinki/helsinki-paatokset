<?php

declare(strict_types=1);

namespace App\AhjoOpenId;

/**
 * Ahjo OpenID settings.
 */
final readonly class Settings {

  public function __construct(
    public string $authUrl,
    public string $tokenUrl,
    public string $clientId,
    public string $scope,
    public string $clientSecret,
  ) {
  }

  /**
   * Asserts that all settings are present.
   *
   * @throws \App\AhjoOpenId\AhjoOpenIdException
   */
  public function assertValid(): void {
    foreach (get_object_vars($this) as $key => $value) {
      if (empty($value)) {
        throw new AhjoOpenIdException(sprintf('Ahjo OpenID is not configured: %s is missing', $key));
      }
    }
  }

}
