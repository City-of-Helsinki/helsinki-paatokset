<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\AhjoOpenId;

use Drupal\Core\Url;
use Drupal\paatokset_ahjo_api\AhjoOpenId\AhjoOpenId;
use Drupal\paatokset_ahjo_api\AhjoOpenId\DTO\AhjoAuthToken;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\paatokset_ahjo_api\Kernel\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests ahjo open id controller.
 */
class AhjoOpenIdControllerTest extends KernelTestBase {

  use ApiTestTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
  ];

  /**
   * Tests callback.
   */
  public function testCallback(): void {
    $this->installEntitySchema('path_alias');

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

    // 403 if use has no permissions.
    $request = $this->getMockedRequest($url->toString());
    $response = $this->processRequest($request);
    $this->assertEquals(403, $response->getStatusCode());

    $this->installEntitySchema('user');
    $this->createUser(['administer ahjo openid']);
    $response = $this->processRequest($request);
    $this->assertTrue($response->isSuccessful());
    $this->assertStringContainsString('Token successfully stored', $response->getContent());
  }

}
