<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel;

use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\paatokset_ahjo_api\Plugin\Deriver\AhjoApiMigrationDeriver;

/**
 * Test for migration deriver.
 */
class MigrationDeriverTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'migrate',
    'file',
    'paatokset_ahjo_openid',
    'paatokset_ahjo_api',
    'paatokset_ahjo_proxy',
    'helfi_api_base',
  ];

  /**
   * {@inheritDoc}
   */
  public function setUp(): void {
    parent::setUp();

    putenv('AHJO_PROXY_BASE_URL=https://ahjo-api-test');
  }

  /**
   * Tests migration deriver.
   */
  public function testDeriver(): void {
    $sut = new AhjoApiMigrationDeriver();
    $derivatives = $sut->getDerivativeDefinitions(['id' => 'ahjo_meetings']);
    $this->assertEquals(['all', 'latest', 'single', 'cancelled'], array_keys($derivatives));
    $this->assertStringEndsWith(Url::fromRoute('paatokset_ahjo_proxy.get_aggregated_data', [
      'dataset' => 'meetings_all',
    ])->toString(), $derivatives['all']['source']['urls'][0]);
  }

}
