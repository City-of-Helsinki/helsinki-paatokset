<?php

namespace Drupal\Tests\paatokset_ahjo_openid\Unit;

use Drupal\paatokset_ahjo_openid\Settings;
use Drupal\paatokset_ahjo_openid\SettingsFactory;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\paatokset_ahjo_openid\SettingsFactory
 * @group paatokset_ahjo_openid
 */
class SettingsTest extends UnitTestCase {

  /**
   * @covers \Drupal\paatokset_ahjo_openid\Settings::__construct
   * @covers ::create
   * @covers ::__construct
   * @dataProvider settingsData
   */
  public function testSettings(array $environment, array $values, array $expectedValues) : void {
    foreach ($environment as $key => $value) {
      putenv("$key=$value");
    }
    $configFactory = $this->getConfigFactoryStub([
      'paatokset_ahjo_openid.settings' => $values,
    ]);

    $sut = new SettingsFactory($configFactory);
    $settings = $sut->create();
    $this->assertInstanceOf(Settings::class, $settings);
    foreach ($expectedValues as $key => $value) {
      $this->assertSame($value, $settings->{$key});
    }
  }

  /**
   * A data provider.
   *
   * @return array[]
   *   The data.
   */
  public function settingsData() : array {
    $values = [
      [
        // Environment.
        [
          'PAATOKSET_OPENID_SECRET' => '123',
        ],
        // Config.
        [
          'auth_url' => 'auth',
          'token_url' => 'token',
          'callback_url' => 'endpoint',
          'client_id' => 'id',
          'scope' => 'scope',
        ],
        // Expected.
        [
          'authUrl' => 'auth',
          'tokenUrl' => 'token',
          'callbackUrl' => 'endpoint',
          'clientId' => 'id',
          'openIdScope' => 'scope',
          'clientSecret' => '123',
        ],
      ],
    ];
    // Make sure invalid values fallback to empty string.
    foreach ([FALSE, NULL, ''] as $value) {
      $values[] = [
        // Environment.
        [
          'PAATOKSET_OPENID_SECRET' => $value,
        ],
        // Config.
        [
          'auth_url' => $value,
          'token_url' => $value,
          'callback_url' => $value,
          'client_id' => $value,
          'scope' => $value,
        ],
        // Expected.
        [
          'authUrl' => '',
          'tokenUrl' => '',
          'callbackUrl' => '',
          'clientId' => '',
          'openIdScope' => '',
          'clientSecret' => '',
        ],
      ];
    }
    return $values;
  }

}
