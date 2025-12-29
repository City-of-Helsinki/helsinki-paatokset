<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\Migrate;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\paatokset_ahjo_proxy\AhjoProxy;
use Drupal\Tests\migrate\Kernel\MigrateSourceTestBase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests migrate source plugin.
 *
 * @covers \Drupal\paatokset_ahjo_api\Plugin\migrate\source\AhjoOrganizationSource
 */
class AhjoOrganizationsSoucePluginTest extends MigrateSourceTestBase {

  use ProphecyTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'paatokset_ahjo_api',
    'helfi_api_base',
  ];

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('ahjo_organization');

    // Makes it possible to mock ahjo proxy.
    putenv('AHJO_PROXY_BASE_URL=test');
  }

  /**
   * {@inheritDoc}
   *
   * @dataProvider providerSource
   */
  public function testSource(array $source_data, array $expected_data, $expected_count = -1, array $configuration = [], $high_water = NULL): void {
    // Setup database.
    foreach (($source_data['db'] ?? []) as $row) {
      $this->container->get(EntityTypeManagerInterface::class)
        ->getStorage('ahjo_organization')
        ->create($row)
        ->save();
    }

    $ahjoProxy = $this->prophesize(AhjoProxy::class);
    $ahjoProxy
      ->isOperational()
      ->willReturn(TRUE);

    // Setup api responses.
    foreach ($source_data['api'] ?? [] as $id => $row) {
      $ahjoProxy->getData(Argument::containingString($id), NULL)
        ->willReturn($row);
    }

    $this->container->set('paatokset_ahjo_proxy', $ahjoProxy->reveal());

    parent::testSource($source_data, $expected_data, $expected_count, $configuration, $high_water);
  }

  /**
   * Data provider for the test.
   */
  public static function providerSource(): array {
    return [
      [
        // Source data.
        [
          'db' => [],
          'api' => [
            '00001' => json_decode(file_get_contents(__DIR__ . '/../../../fixtures/organizations-00001.json'), TRUE),
          ],
        ],
        // Expected data.
        self::getExpectedDataWithLangcodes([
          [
            'id' => '00001',
            'organization_above' => NULL,
          ],
          [
            'id' => '02900',
            'organization_above' => '00001',
          ],
        ]),
      ],
      [
        // Source data.
        [
          'db' => [
            [
              'id' => '00001',
              'title' => 'Kaupunginvaltuusto',
              'changed' => 100,
            ],
            [
              'id' => '02900',
              'title' => 'Kaupunginvaltuusto',
              'changed' => 101,
            ],
          ],
          'api' => [
            // Simulate error.
            '00001' => [],
            '02900' => json_decode(file_get_contents(__DIR__ . '/../../../fixtures/organizations-02900.json'), TRUE),
          ],
        ],
        // Expected data.
        self::getExpectedDataWithLangcodes([
          [
            'id' => '02900',
            'organization_above' => '00001',
          ],
          [
            'id' => '00400',
            'organization_above' => '02900',
          ],
        ]),
      ],
    ];
  }

  /**
   * Adds langcodes to expected data.
   *
   * The process plugins make two requests for each organization. One for
   * each langcode supported by Ahjo API.
   */
  private static function getExpectedDataWithLangcodes(array $expected): array {
    return array_merge(
      array_map(static fn (array $row) => $row + ['langcode' => 'fi'], $expected),
      array_map(static fn (array $row) => $row + ['langcode' => 'sv'], $expected),
    );
  }

}
