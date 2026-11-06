<?php

declare(strict_types=1);

namespace App\Tests\AhjoOpenId;

use App\AhjoOpenId\AhjoOpenIdException;
use App\AhjoOpenId\StateSigner;
use App\Auth\Admin;
use PHPUnit\Framework\TestCase;

/**
 * Tests OpenID state signing.
 */
final class StateSignerTest extends TestCase {

  /**
   * Tests a valid state.
   */
  public function testValidState(): void {
    $signer = new StateSigner('test-secret');
    $state = $signer->create(new Admin('admin-sub', 'client', NULL));

    // Safe to put in a URL as-is.
    $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/', $state);
    $this->assertSame('admin-sub', $signer->verify($state));
  }

  /**
   * Tests that a state signed with another secret is rejected.
   */
  public function testWrongSecret(): void {
    $state = new StateSigner('other-secret')->create(new Admin('admin-sub', 'client', NULL));

    $this->expectException(AhjoOpenIdException::class);
    new StateSigner('test-secret')->verify($state);
  }

  /**
   * Tests that an expired state is rejected.
   */
  public function testExpired(): void {
    $state = new StateSigner('test-secret', ttl: -1)->create(new Admin('admin-sub', 'client', NULL));

    $this->expectExceptionMessage('State has expired.');
    new StateSigner('test-secret')->verify($state);
  }

  /**
   * Tests that nothing is signed or accepted without a secret.
   */
  public function testMissingSecret(): void {
    $signer = new StateSigner('');

    try {
      $signer->create(new Admin('admin-sub', 'client', NULL));
      $this->fail('Expected an exception.');
    }
    catch (AhjoOpenIdException) {
    }

    // HMAC with an empty key is still computable by an attacker.
    $payload = rtrim(strtr(base64_encode('{"sub":"a","exp":' . (time() + 60) . '}'), '+/', '-_'), '=');
    $signature = rtrim(strtr(base64_encode(hash_hmac('sha256', 'ahjo-openid-state|' . $payload, '', TRUE)), '+/', '-_'), '=');

    $this->expectException(AhjoOpenIdException::class);
    $signer->verify($payload . '.' . $signature);
  }

}
