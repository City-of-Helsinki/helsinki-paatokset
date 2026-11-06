<?php

declare(strict_types=1);

namespace App\Tests\AhjoOpenId;

use App\AhjoOpenId\AhjoOpenId;
use App\AhjoOpenId\AhjoOpenIdException;
use App\AhjoOpenId\TokenStorage;
use App\Tests\MockResponseFactory;
use App\Tests\StorageTestTrait;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Tests Ahjo OpenID token refresh.
 */
final class AhjoOpenIdTest extends KernelTestCase {

  use StorageTestTrait;

  /**
   * Gets the service under test.
   */
  private function getOpenId(): AhjoOpenId {
    $service = self::getContainer()->get(AhjoOpenId::class);
    $this->assertInstanceOf(AhjoOpenId::class, $service);

    return $service;
  }

  /**
   * Tests refresh token grant with the console command.
   */
  public function testRefreshCommand(): void {
    MockResponseFactory::setResponse(new MockResponse(json_encode([
      'access_token' => 'new-access',
      'refresh_token' => 'new-refresh',
      'expires_in' => 3600,
    ], JSON_THROW_ON_ERROR)));

    $tester = new CommandTester((new Application(self::bootKernel()))->find('ahjo-proxy:refresh-token'));

    // Missing token.
    $this->assertSame(1, $tester->execute([]));
    $this->assertEmpty(MockResponseFactory::$requests);

    self::setToken('old-access');
    $this->assertSame(0, $tester->execute([]));

    $this->assertCount(1, MockResponseFactory::$requests);
    $requestBody = MockResponseFactory::$requests[0]['options']['body'];
    $this->assertIsString($requestBody);
    parse_str($requestBody, $body);
    $this->assertSame('refresh_token', $body['grant_type']);
    $this->assertSame('refresh-token', $body['refresh_token']);

    $token = (new TokenStorage(self::getStorageDir()))->getToken();
    $this->assertSame('new-access', $token->token);
    $this->assertSame('new-refresh', $token->refreshToken);
    $this->assertFileExists(self::getStorageDir() . '/ahjo-token.old.json');
  }

  /**
   * Tests failed refresh.
   */
  public function testRefreshFailure(): void {
    self::setToken('old-access');
    MockResponseFactory::setResponse(new MockResponse('{"error": "invalid_grant"}', ['http_code' => 400]));

    try {
      $this->getOpenId()->refreshAuthToken();
      $this->fail('Expected exception');
    }
    catch (AhjoOpenIdException) {
    }

    // The used token is cleared.
    $this->assertNull($this->getOpenId()->getAuthToken());
  }

  /**
   * Tests that only one refresh can run at a time.
   */
  public function testRefreshLock(): void {
    self::setToken('old-access');
    MockResponseFactory::setResponse(new MockResponse('{}'));

    $lock = (new TokenStorage(self::getStorageDir()))->createLock('ahjo-auth');
    $this->assertTrue($lock->acquire());

    try {
      $this->getOpenId()->refreshAuthToken();
      $this->fail('Expected exception');
    }
    catch (AhjoOpenIdException $e) {
      $this->assertSame('Failed to acquire lock', $e->getMessage());
    }
    finally {
      $lock->release();
    }

    $this->assertEmpty(MockResponseFactory::$requests);
    $this->assertSame('old-access', $this->getOpenId()->getAuthToken());
  }

}
