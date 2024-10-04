<?php

namespace Drupal\Tests\paatokset_ahjo_openid\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\paatokset_ahjo_openid\AhjoOpenId;
use Drupal\paatokset_ahjo_openid\AhjoOpenIdException;
use Drupal\paatokset_ahjo_openid\Settings;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

/**
 * Kernel tests for paatokset_ahjo_openid.
 *
 * These test functionality that use Drupal State API.
 *
 * @group paatokset_ahjo_openid
 */
class AhjoOpenIdTest extends KernelTestBase {

  use ApiTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'migrate_plus',
    'paatokset_ahjo_openid',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->container->set('paatokset_ahjo_openid.settings', new Settings(
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
    $this->assertNotNull($sut->getAuthAndRefreshTokens('789'));
    $this->assertTrue($sut->checkAuthToken());
    $this->assertEquals('123', $sut->getAuthToken());

    $plugin = $this->container->get('plugin.manager.migrate_plus.authentication')->createInstance('ahjo_openid_token', []);
    $this->assertEquals([
      'headers' => [
        'Authorization' => 'Bearer 123',
      ],
    ], $plugin->getAuthenticationOptions());

    $this->assertEquals('321', $sut->getAuthToken(refresh: TRUE));
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
    $this->assertNotNull($sut->getAuthAndRefreshTokens('789'));
    $this->assertFalse($sut->checkAuthToken());

    // Token should be automatically refreshed.
    $this->assertEquals('321', $sut->getAuthToken());
    $this->assertTrue($sut->checkAuthToken());

    // Second call should not refresh since token should be valid.
    $this->assertEquals('321', $sut->getAuthToken());
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
    $this->assertNotNull($sut->getAuthAndRefreshTokens('789'));

    // Failed to refresh token:
    $plugin = $this->container->get('plugin.manager.migrate_plus.authentication')->createInstance('ahjo_openid_token', []);
    $this->assertEquals([], $plugin->getAuthenticationOptions());

    $this->expectException(AhjoOpenIdException::class);
    $sut->getAuthToken(refresh: TRUE);

  }

}
