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
 * @covers \Drupal\paatokset_ahjo_api\Plugin\migrate\source\AhjoCaseSource
 */
class AhjoCaseSourcePluginTest extends MigrateSourceTestBase {

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

    $this->installEntitySchema('ahjo_case');

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
            '{"cases": []}',
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
            file_get_contents(__DIR__ . '/../../../fixtures/case.json'),
          ],
        ],
        // Expected data.
        [
          [
            'id' => 'HEL-2025-001216',
            'caseIdLabel' => 'HEL 2025-001216',
            'title' => 'Valtuustoaloite, Haltialan kotieläintilasta eläinsuojelukeskus',
            'classificationCode' => '00 00 03',
            'classificationTitle' => 'Valtuuston aloitetoiminta',
            'status' => 'Ratkaistu',
            'language' => 'fi',
            'publicityClass' => 'Julkinen',
          ],
        ],
        // Expected count.
        -1,
        // Configuration.
        [
          'idlist' => ['HEL-2025-001216'],
        ],
      ],
      'date mode' => [
        // Source data.
        [
          'api' => [
            // First call: getCases returns list.
            file_get_contents(__DIR__ . '/../../../fixtures/cases.json'),
            // Subsequent calls: getCase for each case.
            file_get_contents(__DIR__ . '/../../../fixtures/case.json'),
            file_get_contents(__DIR__ . '/../../../fixtures/case.json'),
            file_get_contents(__DIR__ . '/../../../fixtures/case.json'),
            file_get_contents(__DIR__ . '/../../../fixtures/case.json'),
            file_get_contents(__DIR__ . '/../../../fixtures/case.json'),
          ],
        ],
        // Expected data - 5 cases from cases.json, each fetched individually.
        [
          [
            'id' => 'HEL-2025-001216',
            'caseIdLabel' => 'HEL 2025-001216',
            'title' => 'Valtuustoaloite, Haltialan kotieläintilasta eläinsuojelukeskus',
          ],
          [
            'id' => 'HEL-2025-001216',
            'caseIdLabel' => 'HEL 2025-001216',
            'title' => 'Valtuustoaloite, Haltialan kotieläintilasta eläinsuojelukeskus',
          ],
          [
            'id' => 'HEL-2025-001216',
            'caseIdLabel' => 'HEL 2025-001216',
            'title' => 'Valtuustoaloite, Haltialan kotieläintilasta eläinsuojelukeskus',
          ],
          [
            'id' => 'HEL-2025-001216',
            'caseIdLabel' => 'HEL 2025-001216',
            'title' => 'Valtuustoaloite, Haltialan kotieläintilasta eläinsuojelukeskus',
          ],
          [
            'id' => 'HEL-2025-001216',
            'caseIdLabel' => 'HEL 2025-001216',
            'title' => 'Valtuustoaloite, Haltialan kotieläintilasta eläinsuojelukeskus',
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
