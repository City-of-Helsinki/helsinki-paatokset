<?php

declare(strict_types=1);

namespace App\Tests\Auth;

use App\Auth\AccessTokenException;
use App\Auth\AccessTokenVerifier;
use App\Tests\AuthTestTrait;
use App\Tests\MockResponseFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;

/**
 * Tests access token verification.
 */
final class AccessTokenVerifierTest extends TestCase {

  use AuthTestTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    MockResponseFactory::reset();
    self::setUpOidc();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    MockResponseFactory::reset();
    parent::tearDown();
  }

  /**
   * Creates the verifier.
   *
   * @param string $issuer
   *   OIDC issuer.
   * @param list<string> $clients
   *   Allowed clients.
   * @param list<string> $groups
   *   Allowed AD groups.
   */
  private static function createVerifier(string $issuer = self::OIDC_ISSUER, array $clients = ['paatokset-test'], array $groups = ['ahjo-admins']): AccessTokenVerifier {
    return new AccessTokenVerifier(
      new MockHttpClient(new MockResponseFactory()),
      new ArrayAdapter(),
      $issuer,
      $clients,
      $groups,
    );
  }

  /**
   * Tests a valid token.
   */
  public function testValidToken(): void {
    $admin = self::createVerifier(clients: ['other', 'paatokset-test'], groups: ['x', 'ahjo-admins'])->verify(self::createAccessToken());

    $this->assertSame('admin-sub', $admin->sub);
    $this->assertSame('paatokset-test', $admin->azp);
    $this->assertSame('admin@hel.fi', $admin->email);

    // The typ claim is optional.
    $admin = self::createVerifier()->verify(self::createAccessToken(['typ' => NULL, 'email' => NULL]));
    $this->assertNull($admin->email);
  }

  /**
   * Tests that missing configuration denies everything.
   *
   * @param string $issuer
   *   OIDC issuer.
   * @param list<string> $clients
   *   Allowed clients.
   * @param list<string> $groups
   *   Allowed AD groups.
   */
  #[DataProvider('missingConfigurationProvider')]
  public function testMissingConfiguration(string $issuer, array $clients, array $groups): void {
    $this->expectException(\LogicException::class);
    self::createVerifier($issuer, $clients, $groups)->verify(self::createAccessToken());
  }

  /**
   * Data provider for testMissingConfiguration().
   *
   * @return array<string, array{string, list<string>, list<string>}>
   *   Test cases.
   */
  public static function missingConfigurationProvider(): array {
    return [
      'issuer' => ['', ['paatokset-test'], ['ahjo-admins']],
      'clients' => [self::OIDC_ISSUER, [], ['ahjo-admins']],
      'groups' => [self::OIDC_ISSUER, ['paatokset-test'], []],
    ];
  }

  /**
   * Tests that the discovery document must belong to the issuer.
   */
  public function testDiscoveryIssuerMismatch(): void {
    MockResponseFactory::$fixedResponses[self::OIDC_ISSUER . '/.well-known/openid-configuration'] = json_encode([
      'issuer' => 'https://evil.test',
      'jwks_uri' => 'https://evil.test/certs',
    ], JSON_THROW_ON_ERROR);

    $this->expectException(\RuntimeException::class);
    self::createVerifier()->verify(self::createAccessToken());
  }

  /**
   * Tests that the token must not be empty.
   */
  public function testEmptyToken(): void {
    $this->expectException(AccessTokenException::class);
    self::createVerifier()->verify('');
  }

  /**
   * Tests that unsigned tokens are rejected.
   */
  public function testUnsignedToken(): void {
    $b64 = static fn (string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    $token = $b64('{"alg":"none","kid":"test-key"}') . '.' . $b64((string) json_encode([
      'iss' => self::OIDC_ISSUER,
      'sub' => 'admin-sub',
      'azp' => 'paatokset-test',
      'exp' => time() + 300,
      'ad_groups' => ['ahjo-admins'],
    ])) . '.';

    $this->expectException(AccessTokenException::class);
    self::createVerifier()->verify($token);
  }

}
