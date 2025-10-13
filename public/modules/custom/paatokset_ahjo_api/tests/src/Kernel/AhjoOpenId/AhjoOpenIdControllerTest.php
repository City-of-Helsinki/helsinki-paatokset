<?php

declare(strict_types=1);

namespace src\Kernel\AhjoOpenId;

use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\paatokset_ahjo_api\AhjoOpenId\AhjoOpenId;
use Drupal\paatokset_ahjo_api\AhjoOpenId\DTO\AhjoAuthToken;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;

/**
 * Tests ahjo open id controller.
 */
class AhjoOpenIdControllerTest extends KernelTestBase {

  use ApiTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_api_base',
    'paatokset_ahjo_api',
  ];

  /**
   * Tests callback.
   */
  public function testCallback(): void {
    $mock = $this->prophesize(AhjoOpenId::class);
    $this->container->set(AhjoOpenId::class, $mock->reveal());

    // Callback should fetch ahjo token with code query parameter.
    $mock->refreshAuthToken('123')
      ->shouldBeCalled()
      ->willReturn(new AhjoAuthToken('234', 567, '890'));

    $url = Url::fromRoute('paatokset_ahjo_openid.callback', [], [
      'query' => [
        'code' => '123',
      ],
    ]);

    $request = $this->getMockedRequest($url->toString());
    $response = $this->processRequest($request);

    $this->assertTrue($response->isSuccessful());
    $this->assertStringContainsString('Token successfully stored', $response->getContent());
  }

}
