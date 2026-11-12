<?php

declare(strict_types=1);

namespace App\AhjoOpenId;

use App\Auth\Admin;

/**
 * Creates and verifies signed OpenID state values.
 *
 * We need to verify that browser landing on Ahjo OpenID callback url is
 * authorized to replace Ahjo token. The flow is started from Drupal, which
 * passes users access token, which we can verify using Helsinki-profiili
 * public keys.
 *
 * Once verified admin is authorized, this service mints short-lived token
 * signed with APP_SECRET that is added to the callback URL. The callback
 * controller can verify the token before updating the token. This keeps
 * the proxy stateless.
 *
 * Format: base64url(json payload) . '.' . base64url(hmac-sha256).
 */
final readonly class StateSigner {

  /**
   * Prefix that separates these signatures from other uses of the secret.
   */
  private const string CONTEXT = 'ahjo-openid-state|';

  /**
   * Upper bound for the state length.
   */
  private const int MAX_LENGTH = 1024;

  /**
   * Constructs a new instance.
   *
   * @param string $appSecret
   *   The signing secret.
   * @param int $ttl
   *   How long the state is valid, in seconds. It has to cover the time
   *   the admin spends logging in to Ahjo.
   */
  public function __construct(
    #[\SensitiveParameter] private string $appSecret,
    private int $ttl = 600,
  ) {
  }

  /**
   * Creates a state for the admin.
   *
   * @throws \App\AhjoOpenId\AhjoOpenIdException
   */
  public function create(Admin $admin): string {
    $payload = self::encode(json_encode([
      'sub' => $admin->sub,
      'exp' => time() + $this->ttl,
      // Makes every state unique.
      'n' => self::encode(random_bytes(16)),
    ], JSON_THROW_ON_ERROR));

    return $payload . '.' . self::encode($this->sign($payload));
  }

  /**
   * Verifies the state.
   *
   * @return string
   *   Sub of the admin who started the flow.
   *
   * @throws \App\AhjoOpenId\AhjoOpenIdException
   *   If the state is not valid.
   */
  public function verify(string $state): string {
    if (strlen($state) > self::MAX_LENGTH || substr_count($state, '.') !== 1) {
      throw new AhjoOpenIdException('Malformed state.');
    }

    [$payload, $signature] = explode('.', $state);

    // Signature is checked before the payload is parsed.
    if (!hash_equals(self::encode($this->sign($payload)), $signature)) {
      throw new AhjoOpenIdException('Invalid state signature.');
    }

    try {
      $data = json_decode(self::decode($payload), TRUE, flags: JSON_THROW_ON_ERROR);
    }
    catch (\JsonException $e) {
      throw new AhjoOpenIdException('Malformed state.', previous: $e);
    }

    if (!is_array($data) || !is_int($data['exp'] ?? NULL) || !is_string($data['sub'] ?? NULL) || $data['sub'] === '') {
      throw new AhjoOpenIdException('Malformed state.');
    }

    if ($data['exp'] < time()) {
      throw new AhjoOpenIdException('State has expired.');
    }

    return $data['sub'];
  }

  /**
   * Signs the payload.
   *
   * @throws \App\AhjoOpenId\AhjoOpenIdException
   */
  private function sign(string $payload): string {
    // Signing with an empty secret would let anyone forge a state.
    if ($this->appSecret === '') {
      throw new AhjoOpenIdException('Ahjo OpenID is not configured: APP_SECRET is missing');
    }

    return hash_hmac('sha256', self::CONTEXT . $payload, $this->appSecret, TRUE);
  }

  /**
   * Base64url encodes the value.
   */
  private static function encode(string $value): string {
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
  }

  /**
   * Base64url decodes the value.
   */
  private static function decode(string $value): string {
    return (string) base64_decode(strtr($value, '-_', '+/'), TRUE);
  }

}
