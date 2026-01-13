<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\AhjoOpenId\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\paatokset_ahjo_api\AhjoOpenId\AhjoOpenId;
use Drupal\paatokset_ahjo_api\AhjoOpenId\AhjoOpenIdException;
use Drupal\paatokset_ahjo_api\AhjoOpenId\DTO\AhjoAuthToken;
use Drupal\paatokset_ahjo_api\AhjoOpenId\Settings;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel tests for ahjo open id service.
 *
 * These test functionality that uses Drupal State API.
 */
#[Group('paatokset_ahjo_api')]
class AhjoOpenIdTest extends KernelTestBase {

  use ApiTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_api_base',
    'migrate_plus',
    'paatokset_ahjo_api',
    'path_alias',
    'pathauto',
    'token',
    'migrate',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->container->set(Settings::class, new Settings(
      'auth',
      'token',
      'endpoint',
      'id',
      'scope',
      'secret'
    ));
  }

  /**
   * Tests get tokens.
   */
  public function testGetToken(): void {
    $this->setupMockHttpClient([
      new Response(body: json_encode([
        'access_token' => '123',
        'refresh_token' => '456',
        'expires_in' => 300,
      ])),
      new Response(body: json_encode([
        'access_token' => '321',
        'refresh_token' => '456',
        'expires_in' => 300,
      ])),
    ]);

    $sut = $this->container->get(AhjoOpenId::class);
    $this->assertFalse($sut->checkAuthToken());
    $this->assertInstanceOf(AhjoAuthToken::class, $sut->refreshAuthToken('789'));
    $this->assertTrue($sut->checkAuthToken());
    $this->assertEquals('123', $sut->getAuthToken());

    $plugin = $this->container->get('plugin.manager.migrate_plus.authentication')->createInstance('ahjo_openid_token', []);
    $this->assertEquals([
      'headers' => [
        'Authorization' => 'Bearer 123',
      ],
    ], $plugin->getAuthenticationOptions(''));

    // Check that refresh changes the token.
    $this->assertInstanceOf(AhjoAuthToken::class, $sut->refreshAuthToken());
    $this->assertEquals('321', $sut->getAuthToken());
  }

  /**
   * Tests get tokens.
   */
  public function testGetExpiration(): void {
    $this->setupMockHttpClient([
      new Response(body: json_encode([
        'access_token' => '123',
        'refresh_token' => '456',
        // Token expired 1 second ago.
        'expires_in' => -1,
      ])),
      new Response(body: json_encode([
        'access_token' => '321',
        'refresh_token' => '456',
        'expires_in' => 300,
      ])),
    ]);

    $sut = $this->container->get(AhjoOpenId::class);
    $this->assertInstanceOf(AhjoAuthToken::class, $sut->refreshAuthToken('789'));
    $this->assertEquals($sut->getAuthToken(), '123');
    $this->assertFalse($sut->checkAuthToken());

    // Refresh token, this should get the new value.
    $this->assertEquals('321', $sut->refreshAuthToken()->token);
    $this->assertTrue($sut->checkAuthToken());
  }

  /**
   * Tests exceptions.
   */
  public function testExceptions(): void {
    $this->setupMockHttpClient([
      new Response(body: json_encode([
        'access_token' => '123',
        'refresh_token' => '456',
        'expires_in' => -1,
      ])),
      // Token refresh should fail.
      new RequestException('Test exception', new Request('POST', 'test')),
      new RequestException('Test exception', new Request('POST', 'test')),
    ]);

    $sut = $this->container->get(AhjoOpenId::class);
    $this->assertInstanceOf(AhjoAuthToken::class, $sut->refreshAuthToken('789'));
    $this->assertNotEmpty($sut->getAuthToken());

    // Token is expired:
    /** @var \Drupal\paatokset_ahjo_api\Plugin\migrate_plus\authentication\AhjoOpenIdToken $plugin */
    $plugin = $this->container->get('plugin.manager.migrate_plus.authentication')->createInstance('ahjo_openid_token', []);
    $this->assertEquals([], $plugin->getAuthenticationOptions(''));

    $this->expectException(AhjoOpenIdException::class);
    try {
      $sut->refreshAuthToken();
    }
    catch (\Throwable $e) {
      // Botched refresh should remove the token.
      // The old token stops working anyway, so this
      // should make it easier to detect.
      $this->assertEmpty($sut->getAuthToken());

      throw $e;
    }

  }

}
