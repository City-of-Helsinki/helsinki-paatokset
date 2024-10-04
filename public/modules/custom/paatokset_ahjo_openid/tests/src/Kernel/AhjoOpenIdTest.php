<?php

namespace Drupal\Tests\paatokset_ahjo_openid\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\paatokset_ahjo_openid\AhjoOpenId;
use Drupal\paatokset_ahjo_openid\Settings;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use GuzzleHttp\Psr7\Response;

/**
 * Kernel tests for paatokset_ahjo_openid.
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
   * Tests token refreshing.
   */
  public function testGetToken(): void {
    $this->setupMockHttpClient([
      new Response(body: json_encode([
        'access_token' => '123',
        'refresh_token' => '456',
        'expires_in' => 300,
      ])),
    ]);

    $sut = $this->container->get(AhjoOpenId::class);
    $this->assertNotNull($sut->getAuthAndRefreshTokens('789'));
    $this->assertTrue($sut->checkAuthToken());

    $plugin = $this->container->get('plugin.manager.migrate_plus.authentication')->createInstance('ahjo_openid_token', []);
    $this->assertEquals([
      'headers' => [
        'Authorization' => 'Bearer 123',
      ],
    ], $plugin->getAuthenticationOptions());
  }

}
