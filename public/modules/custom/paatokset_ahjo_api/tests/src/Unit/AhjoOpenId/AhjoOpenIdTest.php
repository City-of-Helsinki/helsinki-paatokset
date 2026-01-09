<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\AhjoOpenId\Unit;

use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\State\StateInterface;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\paatokset_ahjo_api\AhjoOpenId\AhjoOpenId;
use Drupal\paatokset_ahjo_api\AhjoOpenId\AhjoOpenIdException;
use Drupal\paatokset_ahjo_api\AhjoOpenId\DTO\AhjoAuthToken;
use Drupal\paatokset_ahjo_api\AhjoOpenId\Settings;
use Drupal\Tests\helfi_api_base\Traits\EnvironmentResolverTrait;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\ClientInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Unit tests for ahjo open id.
 */
#[Group('paatokset_ahjo_api')]
class AhjoOpenIdTest extends UnitTestCase {

  use ProphecyTrait;
  use EnvironmentResolverTrait;

  /**
   * Tests auth url.
   */
  public function testAuthUrl() : void {
    $settings = new Settings(
      'auth',
      'token',
      'endpoint',
      'id',
      'scope',
      'secret'
    );

    $this->assertEquals('auth?client_id=id&scope=scope&response_type=code&redirect_uri=endpoint', $settings->getAuthUrl());
  }

  /**
   * Gets the SUT.
   *
   * @param \Drupal\paatokset_ahjo_api\AhjoOpenId\Settings $settings
   *   Ahjo open id settings.
   * @param \GuzzleHttp\ClientInterface|null $httpClient
   *   The http client.
   * @param \Drupal\Core\State\StateInterface|null $state
   *   The state.
   */
  private function getSut(
    Settings $settings,
    ?ClientInterface $httpClient = NULL,
    ?StateInterface $state = NULL,
  ) : AhjoOpenId {
    if (!$httpClient) {
      $httpClient = $this->prophesize(ClientInterface::class)->reveal();
    }
    if (!$state) {
      $state = $this->prophesize(StateInterface::class)->reveal();
    }

    $lock = $this->prophesize(LockBackendInterface::class)->reveal();
    $environmentResolver = $this->getEnvironmentResolver(Project::PAATOKSET, EnvironmentEnum::Test);

    return new AhjoOpenId($settings, $httpClient, $state, $lock, $environmentResolver);
  }

  /**
   * Tests empty configuration.
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

    $this->expectException(AhjoOpenIdException::class);
    $sut->refreshAuthToken('123');
  }

  /**
   * Tests with valid configuration, but auth flow is not yet configured.
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
      ->get('ahjo-auth-test', Argument::any())
      ->willReturn('');

    $sut = $this->getSut($settings, state: $state->reveal());
    $this->assertFalse($sut->isConfigured());

    $this->expectException(AhjoOpenIdException::class);
    $sut->refreshAuthToken();
  }

  /**
   * Test with valid configuration.
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
      ->get('ahjo-auth-test', Argument::any())
      ->willReturn(json_encode(new AhjoAuthToken('123', time() + 3600, '234')));

    $sut = $this->getSut($settings, state: $state->reveal());
    $this->assertTrue($sut->isConfigured());
  }

  /**
   * Test ahjo auth token DTO.
   */
  public function testAhjoAuthToken() : void {
    $expires = time() - 1;
    $token = new AhjoAuthToken('213', time() - 1, '234');
    $this->assertTrue($token->isExpired());

    // Token is serialized to JSON and stored in the
    // database. Be careful if the format changes.
    $this->assertEquals('{"token":"213","expires":' . $expires . ',"refreshToken":"234"}', json_encode($token));
    $this->assertInstanceOf(AhjoAuthToken::class, AhjoAuthToken::fromJson(json_encode($token)));

    // Ahjo response has slightly different format.
    $token = AhjoAuthToken::fromAhjoResponse((object) ([
      'access_token' => '345',
      'expires_in' => 3600,
      'refresh_token' => '456',
    ]));
    $this->assertInstanceOf(AhjoAuthToken::class, $token);
    // The new token should expire in 1 hour.
    $this->assertFalse($token->isExpired());
  }

  /**
   * Test ahjo auth token DTO.
   */
  #[DataProvider('ahjoAuthTokenErrors')]
  public function testAhjoAuthTokenErrors(string $json) : void {
    $this->expectException(\InvalidArgumentException::class);
    AhjoAuthToken::fromJson($json);
  }

  /**
   * Data provider for testAhjoAuthTokenErrors.
   */
  public static function ahjoAuthTokenErrors() : array {
    return [
      [''],
      ['""'],
      ['123'],
      ['null'],
      ['{}'],
      ['[]'],
      ['{"token":"123"}'],
      ['{"token":"123", "expires":123, "refreshToken":"456"'],
    ];
  }

}
