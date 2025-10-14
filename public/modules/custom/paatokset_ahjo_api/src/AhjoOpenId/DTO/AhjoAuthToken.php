<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\AhjoOpenId\DTO;

/**
 * DTO representing Ahjo auth token.
 */
final readonly class AhjoAuthToken {

  /**
   * Constructs a new instance.
   *
   * @param string $token
   *   The auth token.
   * @param int $expires
   *   Unix timestamp when the token expires.
   * @param string $refreshToken
   *   Token that can be used to fetch new Ahjo token.
   */
  public function __construct(
    public string $token,
    public int $expires,
    public string $refreshToken,
  ) {
  }

  /**
   * Checks if the token is expired.
   */
  public function isExpired(): bool {
    return time() > $this->expires;
  }

  /**
   * Constructs a new instance from Ahjo response.
   *
   * @throws \InvalidArgumentException
   */
  public static function fromAhjoResponse(\stdClass $data): self {
    if (!isset($data->access_token, $data->refresh_token, $data->expires_in)) {
      throw new \InvalidArgumentException('Invalid ahjo data');
    }

    return new self(
      token: $data->access_token,
      expires: time() + $data->expires_in,
      refreshToken: $data->refresh_token,
    );
  }

  /**
   * Deserializes a JSON string.
   *
   * @param string $json
   *   Json string.
   *
   * @throws \InvalidArgumentException
   */
  public static function fromJson(string $json): self {
    try {
      $data = json_decode($json, flags: JSON_THROW_ON_ERROR);

      try {
        /** @var self */
        return (new \ReflectionClass(self::class))
          ->newInstanceArgs((array) $data);
      }
      catch (\ReflectionException | \ArgumentCountError $e) {
        throw new \InvalidArgumentException('Failed to create token: ' . $e->getMessage(), previous: $e);
      }
    }
    catch (\JsonException $e) {
      throw new \InvalidArgumentException($e->getMessage(), previous: $e);
    }
  }

}
