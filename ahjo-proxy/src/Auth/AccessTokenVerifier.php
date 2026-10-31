<?php

declare(strict_types=1);

namespace App\Auth;

use Firebase\JWT\CachedKeySet;
use Firebase\JWT\JWT;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Verifies Helsinki profiili access tokens.
 *
 * Drupal sends the access token of the logged-in admin, which it gets from
 * helfi_tunnistamo. The token is verified against the issuer's signing keys,
 * so the proxy verify that user is allowed to perform actions. Three things
 * have to hold:
 *  - The token is signed by OIDC_ISSUER and has not expired.
 *  - The token was issued to one of the OIDC_ALLOWED_CLIENTS.
 *  - The token's ad_groups claim names one of the OIDC_ALLOWED_AD_GROUPS.
 */
final class AccessTokenVerifier {

  /**
   * How long the signing keys are cached, in seconds.
   *
   * Keys with an unknown kid are fetched immediately, so key rotation
   * does not have to wait for this.
   */
  private const int KEYS_TTL = 3600;

  /**
   * Lazily initialized key set.
   *
   * The discovery document is read once, when the key set is created.
   */
  private ?CachedKeySet $keySet = NULL;

  /**
   * Constructs a new instance.
   *
   * @param \Symfony\Contracts\HttpClient\HttpClientInterface $httpClient
   *   The HTTP client.
   * @param \Psr\Cache\CacheItemPoolInterface $oidcCache
   *   Cache for the signing keys.
   * @param string $oidcIssuer
   *   The issuer.
   * @param list<string> $allowedClients
   *   Allowed client ids.
   * @param list<string> $allowedAdGroups
   *   Allowed AD groups.
   */
  public function __construct(
    private readonly HttpClientInterface $httpClient,
    private readonly CacheItemPoolInterface $oidcCache,
    private readonly string $oidcIssuer,
    private readonly array $allowedClients,
    private readonly array $allowedAdGroups,
  ) {
  }

  /**
   * Verifies the access token.
   *
   * @throws \App\Auth\AccessTokenException
   *   If the token is not acceptable.
   * @throws \LogicException
   *   If the verification is not configured.
   * @throws \RuntimeException
   *   If the signing keys cannot be fetched.
   */
  public function verify(#[\SensitiveParameter] string $token): Admin {
    // Checked before the token, since our own missing configuration
    // must not be reported as a problem with the token.
    if ($this->oidcIssuer === '' || !$this->allowedClients || !$this->allowedAdGroups) {
      throw new \LogicException('Access token verification is not configured: OIDC_ISSUER, OIDC_ALLOWED_CLIENTS and OIDC_ALLOWED_AD_GROUPS are required.');
    }

    if ($token === '') {
      throw new AccessTokenException('The request has no access token.');
    }

    try {
      // Verifies the signature, the algorithm against the key, and exp/nbf
      // claims when present.
      $payload = (array) JWT::decode($token, $this->getKeySet());
    }
    catch (\UnexpectedValueException | \DomainException | \InvalidArgumentException $e) {
      throw new AccessTokenException($e->getMessage(), previous: $e);
    }

    if (($payload['iss'] ?? NULL) !== $this->oidcIssuer) {
      throw new AccessTokenException('The token was not issued by the expected issuer.');
    }

    // The library only checks exp if it is present.
    if (!is_numeric($payload['exp'] ?? NULL)) {
      throw new AccessTokenException('The token has no exp claim.');
    }

    // Access tokens are marked as Bearer. This check should forbid id tokens.
    if (isset($payload['typ']) && $payload['typ'] !== 'Bearer') {
      throw new AccessTokenException(sprintf('Expected a Bearer token, got "%s".', is_string($payload['typ']) ? $payload['typ'] : gettype($payload['typ'])));
    }

    $sub = self::getStringClaim($payload, 'sub');
    $azp = self::getStringClaim($payload, 'azp');

    if (!in_array($azp, $this->allowedClients, TRUE)) {
      throw new AccessTokenException(sprintf('The token was issued to "%s", which is not an allowed client.', $azp));
    }

    $adGroups = is_array($payload['ad_groups'] ?? NULL) ? array_filter($payload['ad_groups'], 'is_string') : [];
    if (!$adGroups) {
      throw new AccessTokenException('The token has no ad_groups claim. The client is likely missing the ad groups scope.');
    }

    if (!array_intersect($this->allowedAdGroups, $adGroups)) {
      throw new AccessTokenException(sprintf('Sub %s is not a member of any allowed AD group.', $sub));
    }

    return new Admin(
      sub: $sub,
      azp: $azp,
      email: is_string($payload['email'] ?? NULL) ? $payload['email'] : NULL,
    );
  }

  /**
   * Gets the signing keys of the issuer.
   *
   * @throws \RuntimeException
   */
  private function getKeySet(): CachedKeySet {
    if (!$this->keySet) {
      $client = new Psr18Client($this->httpClient);

      $this->keySet = new CachedKeySet(
        $this->getJwksUri(),
        $client,
        $client,
        $this->oidcCache,
        self::KEYS_TTL,
        // Limits refetching keys on unknown kid.
        rateLimit: TRUE,
        // Keycloak signs access tokens with RS256 by default.
        defaultAlg: 'RS256',
      );
    }

    return $this->keySet;
  }

  /**
   * Reads the JWKS URI off the issuer's OpenID Connect discovery document.
   *
   * @throws \RuntimeException
   */
  private function getJwksUri(): string {
    // Per OpenID Connect Discovery the path is appended to the issuer,
    // which for a Keycloak realm already has one.
    $discoveryUrl = rtrim($this->oidcIssuer, '/') . '/.well-known/openid-configuration';

    try {
      $document = $this->httpClient->request('GET', $discoveryUrl, [
        'headers' => ['Accept' => 'application/json'],
        'timeout' => 10,
      ])->toArray();
    }
    catch (ExceptionInterface $e) {
      throw new \RuntimeException(sprintf('Failed to read %s: %s', $discoveryUrl, $e->getMessage()), previous: $e);
    }

    // The document must name the issuer we asked about. Anything else means
    // we are not talking to the provider we think we are.
    if (($document['issuer'] ?? NULL) !== $this->oidcIssuer) {
      throw new \RuntimeException(sprintf('%s is not for issuer "%s".', $discoveryUrl, $this->oidcIssuer));
    }

    if (!is_string($document['jwks_uri'] ?? NULL) || $document['jwks_uri'] === '') {
      throw new \RuntimeException(sprintf('%s has no jwks_uri.', $discoveryUrl));
    }

    return $document['jwks_uri'];
  }

  /**
   * Gets a required string claim.
   *
   * @param array<mixed> $payload
   *   Token payload.
   * @param string $claim
   *   Claim name.
   *
   * @throws \App\Auth\AccessTokenException
   */
  private static function getStringClaim(array $payload, string $claim): string {
    $value = $payload[$claim] ?? NULL;

    if (!is_string($value) || $value === '') {
      throw new AccessTokenException(sprintf('The token has no %s claim.', $claim));
    }

    return $value;
  }

}
