<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\AhjoOpenId\Kernel;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\paatokset_ahjo_api\AhjoOpenId\Settings;
use Drupal\paatokset_ahjo_api\AhjoOpenId\SettingsFactory;

/**
 * Tests for Settings.
 *
 * @group paatokset_ahjo_api
 */
class SettingsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_api_base',
    'paatokset_ahjo_api',
  ];

  /**
   * Tests settings.
   *
   * @dataProvider settingsData
   */
  public function testSettings(array $environment, array $values, array $expectedValues) : void {
    foreach ($environment as $key => $value) {
      putenv("$key=$value");
    }

    $this
      ->config('paatokset_ahjo_api.settings')
      ->setData([
        'openid_settings' => $values,
      ])
      ->save();

    $sut = new SettingsFactory(
      $this->container->get(ConfigFactoryInterface::class),
      $this->container->get(LanguageManagerInterface::class)
    );
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
          'client_id' => 'id',
          'scope' => 'scope',
        ],
        // Expected.
        [
          'authUrl' => 'auth',
          'tokenUrl' => 'token',
          'callbackUrl' => 'http://localhost/ahjo-api/login',
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
          'client_id' => $value,
          'scope' => $value,
        ],
        // Expected.
        [
          'authUrl' => '',
          'tokenUrl' => '',
          'callbackUrl' => 'http://localhost/ahjo-api/login',
          'clientId' => '',
          'openIdScope' => '',
          'clientSecret' => '',
        ],
      ];
    }
    return $values;
  }

}
