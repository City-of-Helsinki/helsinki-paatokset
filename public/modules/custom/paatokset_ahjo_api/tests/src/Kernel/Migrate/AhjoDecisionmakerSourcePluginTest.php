<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\Migrate;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\paatokset_ahjo_api\AhjoProxy\AhjoProxyClient;
use Drupal\paatokset_ahjo_api\AhjoProxy\AhjoProxyClientInterface;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\helfi_api_base\Traits\EnvironmentResolverTrait;
use Drupal\Tests\migrate\Kernel\MigrateSourceTestBase;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests migrate source plugin.
 *
 * @covers \Drupal\paatokset_ahjo_api\Plugin\migrate\source\AhjoDecisionmakerSource
 */
class AhjoDecisionmakerSourcePluginTest extends MigrateSourceTestBase {

  use ApiTestTrait;
  use EnvironmentResolverTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'paatokset_ahjo_api',
    'helfi_api_base',
    'json_field',
    'path_alias',
    'pathauto',
    'token',
  ];

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();

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
      'empty api response' => [
        // Source data.
        [
          'api' => [
            // fi.
            '{"decisionMakers": []}',
            // sv.
            '{"decisionMakers": []}',
          ],
        ],
        // Expected data.
        [],
        // Expected count.
        -1,
        // Configuration.
        [
          'after' => '2025-01-01',
          'before' => '2025-01-08',
          'interval' => 'P7D',
        ],
      ],
      'idlist mode' => [
        // Source data.
        [
          'api' => [
            // getOrganization for 02900.
            file_get_contents(__DIR__ . '/../../../fixtures/organizations-02900.json'),
            file_get_contents(__DIR__ . '/../../../fixtures/organizations-02900.json'),
          ],
        ],
        // Expected data.
        [
          [
            'id' => '02900',
            'name' => 'Kaupunginvaltuusto',
            'existing' => TRUE,
            'type_label' => 'Valtuusto',
            'parent_name' => 'Helsingin kaupunki',
            'langcode' => 'fi',
          ],
          [
            'id' => '02900',
            'name' => 'Kaupunginvaltuusto',
            'existing' => TRUE,
            'type_label' => 'Valtuusto',
            'parent_name' => 'Helsingin kaupunki',
            'langcode' => 'sv',
          ],
        ],
        // Expected count.
        -1,
        // Configuration.
        [
          'idlist' => ['02900'],
        ],
      ],
      'date mode' => [
        // Source data.
        [
          'api' => [
            // getDecisionmakers for fi.
            file_get_contents(__DIR__ . '/../../../fixtures/decisionmakers.json'),
            // getDecisionmakers for sv.
            '{"decisionMakers": []}',
          ],
        ],
        // Expected data - 6 decisionmakers from decisionmakers.json.
        [
          [
            'id' => '00400',
            'name' => 'Kaupunginhallitus',
            'existing' => TRUE,
            'type_label' => 'Hallitus',
            'parent_name' => 'Kaupunginvaltuusto',
          ],
          [
            'id' => 'U4804002020VH1',
            'name' => 'Liikuntapaikkapäällikkö',
            'existing' => TRUE,
            'type_label' => 'Viranhaltija',
            'parent_name' => 'Liikuntapaikat',
          ],
          [
            'id' => 'U4804002030VH1',
            'name' => 'Ulkoilupalvelupäällikkö',
            'existing' => TRUE,
            'type_label' => 'Viranhaltija',
            'parent_name' => 'Ulkoilupalvelut',
          ],
          [
            'id' => 'U4804003010VH1',
            'name' => 'Nuorisotyön aluepäällikkö',
            'existing' => TRUE,
            'type_label' => 'Viranhaltija',
            'parent_name' => 'Itäinen nuorisotyö',
          ],
          [
            'id' => 'U4804003020VH1',
            'name' => 'Nuorisotyön aluepäällikkö',
            'existing' => TRUE,
            'type_label' => 'Viranhaltija',
            'parent_name' => 'Läntinen nuorisotyö',
          ],
          [
            'id' => 'U4804003040VH1',
            'name' => 'Kumppanuuspäällikkö',
            'existing' => TRUE,
            'type_label' => 'Viranhaltija',
            'parent_name' => 'Kumppanuusyksikkö',
          ],
        ],
        // Expected count.
        -1,
        // Configuration.
        [
          'after' => '2025-01-01',
          'before' => '2025-01-08',
          'interval' => 'P7D',
        ],
      ],
    ];
  }

}
