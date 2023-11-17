<?php

namespace Drupal\Tests\paatokset_ahjo_openid\Unit;

use Drupal\Core\State\StateInterface;
use Drupal\paatokset_ahjo_openid\AhjoOpenId;
use Drupal\paatokset_ahjo_openid\Settings;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\ClientInterface;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * @coversDefaultClass \Drupal\paatokset_ahjo_openid\AhjoOpenId
 * @group paatokset_ahjo_openid
 */
class AhjoOpenIdTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * Gets the SUT.
   *
   * @param \Drupal\paatokset_ahjo_openid\Settings $settings
   *   The revision manager.
   * @param \GuzzleHttp\ClientInterface|null $httpClient
   *   The entity type manager.
   * @param \Drupal\Core\State\StateInterface|null $state
   *   The connection.
   *
   * @return \Drupal\paatokset_ahjo_openid\AhjoOpenId
   *   The SUT.
   */
  private function getSut(
    Settings $settings,
    ClientInterface $httpClient = NULL,
    StateInterface $state = NULL,
  ) : AhjoOpenId {
    if (!$httpClient) {
      $httpClient = $this->prophesize(ClientInterface::class)->reveal();
    }
    if (!$state) {
      $state = $this->prophesize(StateInterface::class)->reveal();
    }

    return new AhjoOpenId($settings, $httpClient, $state);
  }

  /**
   * @covers ::isConfigured
   * @covers ::getAuthUrl
   * @covers \Drupal\helfi_api_base\Azure\PubSub\Settings::__construct
   */
  public function testNoConfiguration() : void {
    $settings = new Settings(
      '',
      '',
      '',
      '',
      '',
      ''
    );
    $sut = $this->getSut($settings);
    $this->assertFalse($sut->isConfigured());
    $this->assertEmpty($sut->getAuthUrl());
  }

  /**
   * @covers ::isConfigured
   * @covers ::getAuthUrl
   * @covers \Drupal\helfi_api_base\Azure\PubSub\Settings::__construct
   */
  public function testPartialConfiguration() : void {
    $settings = new Settings(
      'auth',
      'token',
      'endpoint',
      'id',
      'scope',
      'secret'
    );
    $state = $this
      ->prophesize(StateInterface::class);
    $state
      ->get('ahjo_api_refresh_token')
      ->willReturn(FALSE);

    $sut = $this->getSut($settings, state: $state->reveal());
    $this->assertFalse($sut->isConfigured());
    $this->assertEquals('auth?client_id=id&scope=scope&response_type=code&redirect_uri=endpoint', $sut->getAuthUrl());
  }

  /**
   * @covers ::isConfigured
   * @covers ::getAuthUrl
   * @covers \Drupal\helfi_api_base\Azure\PubSub\Settings::__construct
   */
  public function testFullConfiguration() : void {
    $settings = new Settings(
      'auth',
      'token',
      'endpoint',
      'id',
      'scope',
      'secret'
    );
    $state = $this
      ->prophesize(StateInterface::class);
    $state
      ->get('ahjo_api_refresh_token')
      ->willReturn('123');

    $sut = $this->getSut($settings, state: $state->reveal());
    $this->assertTrue($sut->isConfigured());
    $this->assertEquals('auth?client_id=id&scope=scope&response_type=code&redirect_uri=endpoint', $sut->getAuthUrl());
  }

}
