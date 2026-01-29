<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\AhjoProxy;

use Drupal\paatokset_ahjo_api\AhjoOpenId\AhjoOpenId;
use Drupal\paatokset_ahjo_api\AhjoProxy\Controller\AhjoProxyController;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\paatokset_ahjo_api\Kernel\KernelTestBase;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\HttpFoundation\Request;

/**
 * Kernel tests for Ahjo proxy controller.
 */
class AhjoProxyControllerTest extends KernelTestBase {

  use ApiTestTrait;

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this
      ->config('paatokset_ahjo_api.settings')
      ->set('ahjo_endpoint', 'https://ahjo.example.com/api')
      ->save();
  }

  /**
   * Tests proxy request.
   */
  public function testProxyRequest(): void {
    $sut = $this->getSut([
      new Response(200, ['Content-Type' => 'application/json'], json_encode(['data' => 'test'])),
      new Response(418, ['Content-Type' => 'application/json'], '{"error": "Not found"}'),
    ]);

    $request = Request::create(
      'https://my-drupal-site.fi/ahjo-proxy/v2/cases/HEL-2024-001',
      'GET',
      ['apireqlang' => 'fi', 'size' => '100']
    );

    $response = $sut->proxyRequest($request, '/ahjo-proxy/v2');

    // Tests successful proxy request.
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals(json_encode(['data' => 'test']), $response->getContent());
    $this->assertEquals('application/json', $response->headers->get('Content-Type'));

    $response = $sut->proxyRequest($request, '/ahjo-proxy/v2');

    // Tests proxy forwards upstream error status codes.
    $this->assertEquals(418, $response->getStatusCode());
  }

  /**
   * Tests proxy returns error when endpoint not configured.
   */
  public function testProxyRequestWithoutEndpoint(): void {
    // Clear the endpoint configuration.
    $this
      ->config('paatokset_ahjo_api.settings')
      ->set('ahjo_endpoint', NULL)
      ->save();

    $sut = $this->getSut([]);

    $request = Request::create(
      'https://my-drupal-site.fi/ahjo-proxy/v2/cases',
      'GET'
    );

    $response = $sut->proxyRequest($request, '/ahjo-proxy/v2');

    $this->assertEquals(500, $response->getStatusCode());
    $this->assertStringContainsString('Ahjo endpoint not configured', $response->getContent());
  }

  /**
   * Get service under test.
   *
   * @param \GuzzleHttp\Psr7\Response[] $responses
   *   Mock responses.
   */
  private function getSut(array $responses): AhjoProxyController {
    $ahjoOpenId = $this->prophesize(AhjoOpenId::class);
    $ahjoOpenId->getAuthToken()->willReturn('test-token');

    return new AhjoProxyController(
      $this->createMockHttpClient($responses),
      $ahjoOpenId->reveal(),
    );
  }

}
