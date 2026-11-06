<?php

declare(strict_types=1);

namespace App\Tests\AhjoOpenId;

use App\AhjoOpenId\AhjoAuthToken;
use PHPUnit\Framework\TestCase;

/**
 * Tests Ahjo auth token DTO.
 */
final class AhjoAuthTokenTest extends TestCase {

  /**
   * Tests token construction and serialization.
   */
  public function testToken(): void {
    $token = AhjoAuthToken::fromAhjoResponse([
      'access_token' => 'access',
      'refresh_token' => 'refresh',
      'expires_in' => 3600,
    ]);

    $this->assertFalse($token->isExpired());
    $this->assertEquals($token, AhjoAuthToken::fromJson(json_encode($token, JSON_THROW_ON_ERROR)));

    $this->assertTrue((new AhjoAuthToken('access', time() - 1, 'refresh'))->isExpired());
  }

  /**
   * Tests invalid input.
   */
  public function testInvalidResponse(): void {
    $this->expectException(\InvalidArgumentException::class);
    AhjoAuthToken::fromAhjoResponse(['access_token' => 'access']);
  }

  /**
   * Tests invalid JSON.
   */
  public function testInvalidJson(): void {
    $this->expectException(\InvalidArgumentException::class);
    AhjoAuthToken::fromJson('{"token": "foo"}');
  }

}
