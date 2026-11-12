<?php

declare(strict_types=1);

namespace App\AhjoOpenId;

/**
 * Ahjo auth token.
 */
final readonly class AhjoAuthToken implements \JsonSerializable {

  public function __construct(
    public string $token,
    public int $expires,
    public string $refreshToken,
  ) {
  }

  /**
   * Checks if the access token has expired.
   */
  public function isExpired(): bool {
    return time() > $this->expires;
  }

  /**
   * Constructs self from Ahjo token endpoint response.
   *
   * @param array<mixed> $data
   *   Decoded token response.
   *
   * @throws \InvalidArgumentException
   */
  public static function fromAhjoResponse(array $data): self {
    if (!isset($data['access_token'], $data['refresh_token'], $data['expires_in'])) {
      throw new \InvalidArgumentException('Invalid ahjo data');
    }

    return new self(
      token: $data['access_token'],
      expires: time() + (int) $data['expires_in'],
      refreshToken: $data['refresh_token'],
    );
  }

  /**
   * Constructs self from serialized JSON.
   *
   * @throws \InvalidArgumentException
   */
  public static function fromJson(string $json): self {
    try {
      $data = json_decode($json, TRUE, flags: JSON_THROW_ON_ERROR);
    }
    catch (\JsonException $e) {
      throw new \InvalidArgumentException($e->getMessage(), previous: $e);
    }

    if (!is_array($data) || !isset($data['token'], $data['expires'], $data['refreshToken'])) {
      throw new \InvalidArgumentException('Invalid token data');
    }

    return new self((string) $data['token'], (int) $data['expires'], (string) $data['refreshToken']);
  }

  /**
   * {@inheritdoc}
   *
   * @return array{token: string, expires: int, refreshToken: string}
   *   Serialized token.
   */
  public function jsonSerialize(): array {
    return [
      'token' => $this->token,
      'expires' => $this->expires,
      'refreshToken' => $this->refreshToken,
    ];
  }

}
