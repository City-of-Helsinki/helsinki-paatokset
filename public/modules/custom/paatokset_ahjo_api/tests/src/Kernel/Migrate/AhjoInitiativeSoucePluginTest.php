<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\Migrate;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drupal\node\Entity\NodeType;
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
 * @covers \Drupal\paatokset_ahjo_api\Plugin\migrate\source\AhjoInitiativeSource
 */
class AhjoInitiativeSoucePluginTest extends MigrateSourceTestBase {

  use ApiTestTrait;
  use EnvironmentResolverTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_api_base',
    'paatokset_ahjo_proxy',
    'file',
    'paatokset_ahjo_api',
    'system',
    'node',
    'user',
    'field',
    'path_alias',
    'pathauto',
    'token',
  ];

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');

    $fieldsToCreate = [
      'field_trustee_id',
    ];

    NodeType::create([
      'type' => 'trustee',
      'name' => 'Trustee',
    ])->save();

    foreach ($fieldsToCreate as $field) {
      FieldStorageConfig::create([
        'field_name' => $field,
        'entity_type' => 'node',
        'type' => 'string',
        'cardinality' => 1,
        'settings' => [],
      ])->save();

      FieldConfig::create([
        'field_name' => $field,
        'entity_type' => 'node',
        'bundle' => 'trustee',
        'label' => 'Trustee',
        'settings' => [],
      ])->save();
    }

    // Replace environment resolver.
    $environmentResolver = $this->getEnvironmentResolver(
      Project::PAATOKSET,
      EnvironmentEnum::Test->value
    );

    $this->installEntitySchema('path_alias');

    $this->container->set(EnvironmentResolverInterface::class, $environmentResolver);
  }

  /**
   * Tests migration configuration.
   */
  public function testMigration(): void {
    $migration = $this->container
      ->get(MigrationPluginManagerInterface::class)
      ->createInstance('ahjo_initiatives', []);

    $source = $migration->getSourcePlugin();
    $this->assertInstanceOf($this->getPluginClass(), $source);

    // Verify that this source defines all migration fields.
    foreach ($migration->getProcess() as $process) {
      foreach ($process as $definition) {
        if (isset($definition['source'])) {
          $this->assertArrayHasKey($definition['source'], $source->fields());
        }
      }
    }
  }

  /**
   * {@inheritDoc}
   */
  #[DataProvider('providerSource')]
  public function testSource(array $source_data, array $expected_data, $expected_count = -1, array $configuration = [], $high_water = NULL): void {
    // Setup database.
    foreach (($source_data['db'] ?? []) as $row) {
      $this->container->get(EntityTypeManagerInterface::class)
        ->getStorage('node')
        ->create($row)
        ->save();
    }

    // Setup mock http_client.
    if (!empty($source_data['responses'])) {
      $this->container->set(AhjoProxyClientInterface::class, new AhjoProxyClient(
        $this->createMockHttpClient(
          array_map(static fn (string $body) => new Response(body: $body), $source_data['responses'])
        ),
        $this->container->get(EnvironmentResolverInterface::class),
        $this->container->get(ConfigFactoryInterface::class),
        $this->container->get('logger.channel.paatokset_ahjo_api'),
      ));
    }

    parent::testSource($source_data, $expected_data, $expected_count, $configuration, $high_water);
  }

  /**
   * Data provider for the test.
   */
  public static function providerSource(): array {
    return [
      [
        // Source data.
        [],
        // Expected data.
        [],
      ],
      [
        // Source data.
        [
          'responses' => [
            file_get_contents(__DIR__ . '/../../../fixtures/trustee.json'),
          ],
          'db' => [
            [
              'type' => 'trustee',
              'id' => '00001',
              'title' => 'Kaupunginvaltuusto',
              'changed' => 100,
            ],
          ],
        ],
        // Expected data.
        [
          [
            'Title' => 'Valtuustoaloite 22.09.2021 Stansvikin kaavan muuttaminen',
            'Date' => 1632243600,
            'FileURI' => 'https://ahjojulkaisu.hel.fi/E764C79F-DA18-C784-9A76-7C0EE8800000.pdf',
            'Trustee' => 'test-trustee',
            'Trustee_NID' => '1',
          ],
        ],
      ],
    ];
  }

}
