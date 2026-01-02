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
use Drupal\paatokset_ahjo_api\AhjoProxy\DTO\Organization;
use Drupal\paatokset_ahjo_api\AhjoProxy\DTO\OrganizationNode;
use Drupal\paatokset_ahjo_api\AhjoProxy\DTO\Trustee;
use Drupal\paatokset_ahjo_api\Entity\OrganizationType;
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
    'path_alias',
    'pathauto',
    'token',
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
   * Tests ahjo proxy client trustees.
   */
  public function testTrusteeClient(): void {
    $sut = $this->getSut([
      new Response(200, [], file_get_contents(dirname(__DIR__, 3) . '/fixtures/trustee.json')),
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
   * Tests ahjo proxy client organizations.
   */
  public function testOrganizationClient(): void {
    $sut = $this->getSut([
      new Response(200, [], file_get_contents(dirname(__DIR__, 3) . '/fixtures/organizations-00001.json')),
      new Response(200, [], file_get_contents(dirname(__DIR__, 3) . '/fixtures/organizations-02900.json')),
      new ClientException('test-error', new Request('GET', '/test'), new Response()),
    ]);

    $organization = $sut->getOrganization('fi', '00001');
    $this->assertInstanceOf(OrganizationNode::class, $organization);

    // Hard coded to match the json file.
    $this->assertCount(1, $organization->children);
    $this->assertNull($organization->parent);
    $this->assertEquals('00001', $organization->organization->id);
    $this->assertEquals(OrganizationType::CITY, $organization->organization->type);
    $this->assertEquals('Helsingin kaupunki', $organization->organization->name);
    $this->assertCount(1, $organization->children);

    foreach ($organization->children as $child) {
      $this->assertInstanceOf(Organization::class, $child);
    }

    $organization = $sut->getOrganization('fi', '02900');
    $this->assertInstanceOf(OrganizationNode::class, $organization);

    // Hard coded to match the json file.
    $this->assertCount(1, $organization->children);
    $this->assertNotNull($organization->parent);
    $this->assertEquals('02900', $organization->organization->id);
    $this->assertEquals(OrganizationType::COUNCIL, $organization->organization->type);
    $this->assertEquals('Kaupunginvaltuusto', $organization->organization->name);
    $this->assertCount(1, $organization->children);

    foreach ($organization->children as $child) {
      $this->assertInstanceOf(Organization::class, $child);
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
