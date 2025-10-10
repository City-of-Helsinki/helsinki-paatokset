<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\AhjoProxy;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\KernelTests\KernelTestBase;
use Drupal\paatokset_ahjo_api\AhjoProxy\AhjoProxyClient;
use Drupal\paatokset_ahjo_api\AhjoProxy\AhjoProxyClientInterface;
use Drupal\paatokset_ahjo_api\AhjoProxy\AhjoProxyException;
use Drupal\paatokset_ahjo_api\AhjoProxy\DTO\AhjojulkaisuDocument;
use Drupal\paatokset_ahjo_api\AhjoProxy\DTO\Chairmanship;
use Drupal\paatokset_ahjo_api\AhjoProxy\DTO\Trustee;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\helfi_api_base\Traits\EnvironmentResolverTrait;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

/**
 * Kernel tests for Ahjo proxy client.
 */
class AhjoProxyClientTest extends KernelTestBase {

  use ApiTestTrait;
  use EnvironmentResolverTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_api_base',
    'paatokset_ahjo_api',
  ];

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this
      ->config('paatokset_ahjo_api.settings')
      ->set('proxy_api_key', '123')
      ->save();
  }

  /**
   * Tests ahjo proxy client error.
   */
  public function testAhjoProxyClient(): void {
    $sut = $this->getSut([
      new Response(200, [], file_get_contents(__DIR__ . '/../../../fixtures/trustee.json')),
      new ClientException('test-error', new Request('GET', '/test'), new Response()),
    ]);

    $trustee = $sut->getTrustee('fi', 'test-trustee');
    $this->assertInstanceOf(Trustee::class, $trustee);

    // Hard coded to match trustee.json file.
    $this->assertCount(1, $trustee->initiatives);
    $this->assertCount(1, $trustee->resolutions);
    $this->assertCount(1, $trustee->chairmanships);
    $this->assertEquals('test-trustee', $trustee->id);

    foreach ([...$trustee->initiatives, ...$trustee->resolutions] as $initiative) {
      $this->assertInstanceOf(AhjojulkaisuDocument::class, $initiative);
    }

    foreach ($trustee->chairmanships as $chairmanship) {
      $this->assertInstanceOf(Chairmanship::class, $chairmanship);
    }

    $this->expectException(AhjoProxyException::class);
    $sut->getTrustee('fi', 'test-trustee');
  }

  /**
   * Get service under test.
   */
  private function getSut(array $responses): AhjoProxyClientInterface {
    $environmentResolver = $this->getEnvironmentResolver(
      Project::PAATOKSET,
      EnvironmentEnum::Test->value
    );

    return new AhjoProxyClient(
      $this->createMockHttpClient($responses),
      $environmentResolver,
      $this->container->get(ConfigFactoryInterface::class),
    );
  }

}
