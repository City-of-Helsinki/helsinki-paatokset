<?php

declare(strict_types=1);

namespace Drupal\paatokset_allu\Client;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\State\StateInterface;
use Drupal\paatokset_allu\AlluException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Allu token factory.
 */
class TokenFactory {

  public const TOKEN_STATE = 'paatokset_allu_token';

  /**
   * Creates a new instance.
   *
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time.
   * @param \GuzzleHttp\ClientInterface $client
   *   The client.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state.
   * @param \Drupal\paatokset_allu\Client\Settings $settings
   *   The Allu settings.
   */
  public function __construct(
    private readonly TimeInterface $time,
    private readonly ClientInterface $client,
    private readonly StateInterface $state,
    private readonly Settings $settings,
  ) {
  }

  /**
   * Get token.
   *
   * @return string
   *   Token.
   *
   * @throws \Drupal\paatokset_allu\AlluException
   */
  public function getToken(): string {
    $token = $this->state->get(self::TOKEN_STATE);
    if ($this->isValidToken($token)) {
      return trim($token, "\"");
    }

    try {
      $token = $this->getNewToken();
    }
    catch (GuzzleException $e) {
      throw new AlluException("Failed to retrieve token: " . $e->getMessage(), $e->getCode(), $e);
    }

    if (!$this->isValidToken($token)) {
      throw new AlluException("Failed to retrieve token");
    }

    $this->state->set(self::TOKEN_STATE, $token);

    return trim($token, "\"");
  }

  /**
   * Check if token is valid.
   *
   * Allu tokens are JWT tokens. Check the expiration date from JWT payload.
   *
   * @param ?string $token
   *   Allu token.
   * @param int $leeway
   *   Number of seconds before the actual expiration date when the tokens are
   *   considers expired.
   *
   * @return bool
   *   TRUE if token is valid.
   */
  private function isValidToken(?string $token, int $leeway = 10): bool {
    if (empty($token)) {
      return FALSE;
    }

    $parts = explode('.', $token);

    // Malformed JWT.
    if (count($parts) !== 3) {
      return FALSE;
    }

    [, $payload] = $parts;

    try {
      $decoded = json_decode(base64_decode($payload), flags: JSON_THROW_ON_ERROR);

      return isset($decoded->exp) && $decoded->exp - $leeway >= $this->time->getCurrentTime();
    }
    catch (\JsonException $e) {
      return FALSE;
    }
  }

  /**
   * Get new token from Allu API.
   *
   * @return string
   *   Allu token.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  private function getNewToken(): string {
    $response = $this->client->request('POST', "{$this->settings->baseUrl}/external/v2/login", [
      'json' => [
        'username' => $this->settings->username,
        'password' => $this->settings->password,
      ],
    ]);

    return $response->getBody()->getContents();
  }

}
