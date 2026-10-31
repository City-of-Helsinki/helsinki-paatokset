<?php

declare(strict_types=1);

namespace App\Auth;

/**
 * Verifies the api key.
 *
 * The key is sent in the `api-key` header, like with Drupal key_auth
 * module that was used with the previous proxy versions.
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
