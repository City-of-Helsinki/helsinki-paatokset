<?php

declare(strict_types=1);

namespace App\Tests;

use Firebase\JWT\JWT;

/**
 * Provides a fake OIDC issuer and signed access tokens.
 *
 * Mirrors the test helpers of helfi-hakuvahti.
 */
trait AuthTestTrait {

  protected const string OIDC_ISSUER = 'https://tunnistus.test/auth/realms/helsinki-tunnistus';
  protected const string OIDC_KID = 'test-key';

  /**
   * The signing key, shared by all tests.
   */
  private static ?\OpenSSLAsymmetricKey $signingKey = NULL;

  /**
   * Gets the signing key.
   */
  private static function getSigningKey(): \OpenSSLAsymmetricKey {
    if (!self::$signingKey) {
      $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
      assert($key instanceof \OpenSSLAsymmetricKey);
      self::$signingKey = $key;
    }

    return self::$signingKey;
  }

  /**
   * Serves the discovery document and the signing keys of the issuer.
   */
  protected static function setUpOidc(): void {
    $details = openssl_pkey_get_details(self::getSigningKey());
    assert(is_array($details));

    $b64 = static fn (string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');

    MockResponseFactory::$fixedResponses[self::OIDC_ISSUER . '/.well-known/openid-configuration'] = json_encode([
      'issuer' => self::OIDC_ISSUER,
      'jwks_uri' => self::OIDC_ISSUER . '/protocol/openid-connect/certs',
    ], JSON_THROW_ON_ERROR);

    MockResponseFactory::$fixedResponses[self::OIDC_ISSUER . '/protocol/openid-connect/certs'] = json_encode([
      'keys' => [
        [
          'kty' => 'RSA',
          'use' => 'sig',
          'alg' => 'RS256',
          'kid' => self::OIDC_KID,
          'n' => $b64($details['rsa']['n']),
          'e' => $b64($details['rsa']['e']),
        ],
      ],
    ], JSON_THROW_ON_ERROR);
  }

  /**
   * Creates a signed access token.
   *
   * @param array<string, mixed> $claims
   *   Claims that override the defaults. NULL value removes the claim.
   * @param \OpenSSLAsymmetricKey|null $key
   *   Signing key. Defaults to the issuer's key.
   */
  protected static function createAccessToken(array $claims = [], ?\OpenSSLAsymmetricKey $key = NULL): string {
    $payload = array_filter([
      'iss' => self::OIDC_ISSUER,
      'sub' => 'admin-sub',
      'azp' => 'paatokset-test',
      'typ' => 'Bearer',
      'exp' => time() + 300,
      'iat' => time(),
      'email' => 'admin@hel.fi',
      'ad_groups' => ['some-group', 'ahjo-admins'],
      ...$claims,
    ], static fn (mixed $value) => $value !== NULL);

    return JWT::encode($payload, $key ?? self::getSigningKey(), 'RS256', self::OIDC_KID);
  }

}
