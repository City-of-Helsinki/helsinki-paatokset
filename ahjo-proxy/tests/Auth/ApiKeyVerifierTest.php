<?php

declare(strict_types=1);

namespace App\Tests\Auth;

use App\Auth\ApiKeyVerifier;
use PHPUnit\Framework\TestCase;

/**
 * Tests api key verification.
 */
final class ApiKeyVerifierTest extends TestCase {

  /**
   * Tests api key verification.
   */
  public function testVerify(): void {
    $verifier = new ApiKeyVerifier('test-api-key');
    $this->assertTrue($verifier->verify('test-api-key'));
    $this->assertFalse($verifier->verify('wrong'));
    $this->assertFalse($verifier->verify(''));
  }

  /**
   * Tests that missing configuration denies everything.
   */
  public function testMissingConfiguration(): void {
    $this->assertFalse(new ApiKeyVerifier('')->verify(''));
  }

}
