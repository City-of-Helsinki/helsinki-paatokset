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
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests migrate source plugin.
 *
 * @covers \Drupal\paatokset_ahjo_api\Plugin\migrate\source\AhjoOrgCompositionSource
 */
class AhjoOrgCompositionSourcePluginTest extends MigrateSourceTestBase {

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
    'system',
    'user',
    'options',
    'node',
    'paatokset_policymakers',
    'file',
    'text',
    'field',
    'datetime',
  ];

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('path_alias');
    $this->installSchema('node', 'node_access');
    $this->installConfig('paatokset_ahjo_api');

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
    // Create policymaker nodes.
    $storage = $this->container->get(EntityTypeManagerInterface::class)->getStorage('node');
    foreach ($source_data['nodes'] ?? [] as $nodeData) {
      $storage->create($nodeData)->save();
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
      'no policymaker nodes' => [
        // Source data.
        [
          'nodes' => [],
          'api' => [],
        ],
        // Expected data.
        [],
        // Expected count.
        -1,
        // Configuration.
        [
          'orgs' => 'all',
        ],
      ],
      'active orgs mode' => [
        // Source data.
        [
          'nodes' => [
            [
              'type' => 'policymaker',
              'status' => 1,
              'title' => 'Kaupunginvaltuusto',
              'field_policymaker_id' => '02900',
              'field_organization_type' => 'Valtuusto',
              'field_policymaker_existing' => TRUE,
            ],
            // Inactive org should be excluded.
            [
              'type' => 'policymaker',
              'status' => 1,
              'title' => 'Inactive org',
              'field_policymaker_id' => '99999',
              'field_organization_type' => 'Hallitus',
              'field_policymaker_existing' => FALSE,
            ],
          ],
          'api' => [
            // getDecisionmaker for 02900.
            file_get_contents(__DIR__ . '/../../../fixtures/decisionmaker-02900.json'),
          ],
        ],
        // Expected data - only the active org.
        [
          [
            'id' => '02900',
          ],
        ],
        // Expected count.
        -1,
        // Configuration.
        [
          'orgs' => 'active',
        ],
      ],
      'all orgs mode' => [
        // Source data.
        [
          'nodes' => [
            [
              'type' => 'policymaker',
              'status' => 1,
              'title' => 'Kaupunginvaltuusto',
              'field_policymaker_id' => '02900',
              'field_organization_type' => 'Valtuusto',
              'field_policymaker_existing' => TRUE,
            ],
            [
              'type' => 'policymaker',
              'status' => 1,
              'title' => 'Inactive org',
              'field_policymaker_id' => '00400',
              'field_organization_type' => 'Hallitus',
              'field_policymaker_existing' => FALSE,
            ],
          ],
          'api' => [
            // getDecisionmaker for each org.
            file_get_contents(__DIR__ . '/../../../fixtures/decisionmaker-02900.json'),
            file_get_contents(__DIR__ . '/../../../fixtures/decisionmaker-02900.json'),
          ],
        ],
        // Expected data - both orgs.
        [
          [
            'id' => '02900',
          ],
          [
            'id' => '02900',
          ],
        ],
        // Expected count.
        -1,
        // Configuration.
        [
          'orgs' => 'all',
        ],
      ],
      'ids mode' => [
        // Source data.
        [
          'nodes' => [
            [
              'type' => 'policymaker',
              'status' => 1,
              'title' => 'Kaupunginvaltuusto',
              'field_policymaker_id' => '02900',
              'field_organization_type' => 'Valtuusto',
              'field_policymaker_existing' => TRUE,
            ],
          ],
          'api' => [
            file_get_contents(__DIR__ . '/../../../fixtures/decisionmaker-02900.json'),
          ],
        ],
        // Expected data.
        [
          [
            'id' => '02900',
          ],
        ],
        // Expected count.
        -1,
        // Configuration.
        [
          'idlist' => ['02900'],
        ],
      ],
    ];
  }

}
