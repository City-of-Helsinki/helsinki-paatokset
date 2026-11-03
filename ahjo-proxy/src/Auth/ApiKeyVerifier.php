<?php

declare(strict_types=1);

namespace App\Auth;

/**
 * Verifies the api key.
 */
final readonly class ApiKeyVerifier {

  public function __construct(
    #[\SensitiveParameter] private string $apiKey,
  ) {
  }

  /**
   * Checks the api key.
   */
  public function verify(#[\SensitiveParameter] string $key): bool {
    // Deny everything if the api key is not configured.
    return $this->apiKey !== '' && hash_equals($this->apiKey, $key);
  }

}
