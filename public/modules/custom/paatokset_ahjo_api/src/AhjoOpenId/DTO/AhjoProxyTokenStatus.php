<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\AhjoOpenId\DTO;

/**
 * DTO representing the Ahjo proxy `GET /openid` response.
 */
final readonly class AhjoProxyTokenStatus {

  /**
   * Constructs a new instance.
   *
   * @param int|null $expires
   *   Timestamp when the token expires, or NULL if the token is not set.
   */
  public function __construct(
    public ?int $expires,
  ) {
  }

  /**
   * Checks if the proxy has a token.
   */
  public function hasToken(): bool {
    return $this->expires !== NULL;
  }

  /**
   * Checks if the token is expired.
   */
  public function isExpired(): bool {
    return $this->expires !== NULL && time() > $this->expires;
  }

  /**
   * Constructs a new instance from the proxy response.
   *
   * @throws \InvalidArgumentException
   */
  public static function fromJson(string $json): self {
    try {
      $data = json_decode($json, flags: JSON_THROW_ON_ERROR);
    }
    catch (\JsonException $e) {
      throw new \InvalidArgumentException('Invalid JSON: ' . $e->getMessage(), previous: $e);
    }

    if (!$data instanceof \stdClass) {
      throw new \InvalidArgumentException('Invalid response');
    }

    return new self(
      expires: is_int($data->expires ?? FALSE) ? $data->expires : NULL
    );
  }

}
