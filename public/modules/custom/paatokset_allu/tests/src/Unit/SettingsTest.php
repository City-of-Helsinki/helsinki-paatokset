<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_allu\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\helfi_api_base\Vault\Json;
use Drupal\helfi_api_base\Vault\VaultManager;
use Drupal\paatokset_allu\Client\SettingsFactory;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests Allu settings.
 *
 * @group paatokset_allu
 */
class SettingsTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * Tests settings.
   *
   * @dataProvider settingsData
   */
  public function testSettings(array $vault, array $configuration, array $expectedValues): void {
    $vaultManager = new VaultManager([
      new Json('allu', json_encode($vault)),
    ]);

    $config = $this->prophesize(ImmutableConfig::class);
    $config
      ->get(Argument::type('string'))
      ->will(fn ($args) => $configuration[$args[0]]);
    $configFactory = $this->prophesize(ConfigFactoryInterface::class);
    $configFactory->get('paatokset_allu.settings')->willReturn($config->reveal());

    $sut = new SettingsFactory($vaultManager, $configFactory->reveal());
    $settings = $sut->create();
    foreach ($expectedValues as $name => $expectedValue) {
      $this->assertSame($expectedValue, $settings->{$name});
    }
  }

  /**
   * A data provider.
   */
  public function settingsData() : array {
    $values = [
      [
        [
          'username' => 'foo',
          'password' => 'bar',
        ],
        [
          'base_url' => 'https://example.com',
        ],
        [
          'username' => 'foo',
          'password' => 'bar',
          'baseUrl' => 'https://example.com',
        ],
      ],
    ];
    // Make sure invalid values fallback to empty string.
    foreach ([FALSE, NULL, ''] as $value) {
      $values[] = [
        [
          'username' => $value,
          'password' => $value,
        ],
        [
          'base_url' => $value,
        ],
        [
          'username' => '',
          'password' => '',
          'baseUrl' => '',
        ],
      ];
    }
    return $values;

  }

}
