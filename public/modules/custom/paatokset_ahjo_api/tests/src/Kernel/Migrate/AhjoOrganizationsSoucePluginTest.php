<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\Migrate;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\paatokset_ahjo_api\AhjoProxy\AhjoProxyClient;
use Drupal\paatokset_ahjo_api\AhjoProxy\AhjoProxyClientInterface;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\helfi_api_base\Traits\EnvironmentResolverTrait;
use Drupal\Tests\migrate\Kernel\MigrateSourceTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use GuzzleHttp\Psr7\Response;

/**
 * Tests migrate source plugin.
 *
 * @covers \Drupal\paatokset_ahjo_api\Plugin\migrate\source\AhjoOrganizationSource
 */
class AhjoOrganizationsSoucePluginTest extends MigrateSourceTestBase {

  use ApiTestTrait;
  use EnvironmentResolverTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'paatokset_ahjo_api',
    'helfi_api_base',
    'path_alias',
    'pathauto',
    'token',
  ];

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('ahjo_organization');

    // Replace environment resolver.
    $environmentResolver = $this->getEnvironmentResolver(
      Project::PAATOKSET,
      EnvironmentEnum::Test->value
    );

    $this->container->set(EnvironmentResolverInterface::class, $environmentResolver);
  }

  /**
   * {@inheritDoc}
   */
  #[DataProvider('providerSource')]
  public function testSource(array $source_data, array $expected_data, $expected_count = -1, array $configuration = [], $high_water = NULL): void {
    // Setup database.
    foreach (($source_data['db'] ?? []) as $row) {
      $this->container->get(EntityTypeManagerInterface::class)
        ->getStorage('ahjo_organization')
        ->create($row)
        ->save();
    }

    // Setup api responses.
    $this->container->set(AhjoProxyClientInterface::class, new AhjoProxyClient(
      $this->createMockHttpClient(
        array_map(static fn (string $body) => new Response(body: $body), $source_data['api'] ?? [])
      ),
      $this->container->get(EnvironmentResolverInterface::class),
      $this->container->get(ConfigFactoryInterface::class),
      $this->container->get('logger.channel.paatokset_ahjo_api'),
    ));

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
            '00001_fi' => file_get_contents(__DIR__ . '/../../../fixtures/organizations-00001.json'),
            '00001_sv' => file_get_contents(__DIR__ . '/../../../fixtures/organizations-00001.json'),
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
            '00001_fi' => '',
            '00001_sv' => '',
            '02900_fi' => file_get_contents(__DIR__ . '/../../../fixtures/organizations-02900.json'),
            '02900_sv' => file_get_contents(__DIR__ . '/../../../fixtures/organizations-02900.json'),
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
