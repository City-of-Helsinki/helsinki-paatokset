<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\AhjoOpenId\Unit;

use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueMemoryFactory;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\paatokset_ahjo_api\AhjoOpenId\AhjoOpenId;
use Drupal\paatokset_ahjo_api\AhjoOpenId\AhjoOpenIdException;
use Drupal\paatokset_ahjo_api\AhjoOpenId\DTO\AhjoAuthToken;
use Drupal\paatokset_ahjo_api\AhjoOpenId\Settings;
use Drupal\Tests\helfi_api_base\Traits\EnvironmentResolverTrait;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Psr7\Response;
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
   * Key of the token in the keyvalue collection.
   */
  private const TOKEN_KEY = 'test';

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

    $sut = $this->getSut($settings);
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
    $keyValueFactory = $this->getSeededKeyValueFactory(new AhjoAuthToken('123', time() + 3600, '234'));

    $sut = $this->getSut($settings, keyValueFactory: $keyValueFactory);
    $this->assertTrue($sut->isConfigured());
  }

  /**
   * Tests that a failed refresh keeps the previous token intact.
   */
  public function testRefreshFailurePreservesToken() : void {
    $settings = new Settings(
      'auth',
      'token',
      'endpoint',
      'id',
      'scope',
      'secret'
    );
    $token = new AhjoAuthToken('123', time() + 3600, '234');
    $keyValueFactory = $this->getSeededKeyValueFactory($token);

    $httpClient = $this->prophesize(ClientInterface::class);
    $httpClient
      ->request('POST', 'token', Argument::any())
      ->willThrow(new TransferException('Connection timeout'));

    $sut = $this->getSut($settings, $httpClient->reveal(), $keyValueFactory);

    $exception = NULL;
    try {
      $sut->refreshAuthToken();
    }
    catch (AhjoOpenIdException $exception) {
    }
    $this->assertInstanceOf(AhjoOpenIdException::class, $exception);

    // The previous token and its refresh token must remain usable so the
    // next refresh attempt can recover from a transient failure.
    $this->assertEquals(json_encode($token), $keyValueFactory->get(AhjoOpenId::TOKEN_COLLECTION)->get(self::TOKEN_KEY));
    $this->assertEquals('123', $sut->getAuthToken());
    $this->assertTrue($sut->isConfigured());
  }

  /**
   * Tests that a successful refresh stores the new token.
   */
  public function testSuccessfulRefreshStoresToken() : void {
    $settings = new Settings(
      'auth',
      'token',
      'endpoint',
      'id',
      'scope',
      'secret'
    );
    $keyValueFactory = $this->getSeededKeyValueFactory(new AhjoAuthToken('123', time() - 1, '234'));

    $httpClient = $this->prophesize(ClientInterface::class);
    $httpClient
      ->request('POST', 'token', Argument::any())
      ->willReturn(new Response(200, [], (string) json_encode([
        'access_token' => 'new-token',
        'refresh_token' => 'new-refresh-token',
        'expires_in' => 3600,
      ])));

    $lock = $this->prophesize(LockBackendInterface::class);
    $lock->acquire('ahjo-auth-test')->willReturn(TRUE)->shouldBeCalled();
    $lock->release('ahjo-auth-test')->shouldBeCalled();
    $lock->wait(Argument::cetera())->willReturn(FALSE);

    $sut = $this->getSut($settings, $httpClient->reveal(), $keyValueFactory, $lock->reveal());

    $token = $sut->refreshAuthToken();
    $this->assertEquals('new-token', $token->token);
    $this->assertEquals('new-refresh-token', $token->refreshToken);
    $this->assertEquals(json_encode($token), $keyValueFactory->get(AhjoOpenId::TOKEN_COLLECTION)->get(self::TOKEN_KEY));
    $this->assertEquals('new-token', $sut->getAuthToken());
  }

  /**
   * Tests that readers wait for an in-flight refresh and re-read.
   */
  public function testWaitAndReread() : void {
    $settings = new Settings(
      'auth',
      'token',
      'endpoint',
      'id',
      'scope',
      'secret'
    );
    $keyValueFactory = new KeyValueMemoryFactory();
    $store = $keyValueFactory->get(AhjoOpenId::TOKEN_COLLECTION);

    // Simulate a concurrent process finishing a token refresh while
    // this process is waiting on the refresh lock.
    $lock = $this->prophesize(LockBackendInterface::class);
    $lock
      ->wait('ahjo-auth-test')
      ->will(function () use ($store) : bool {
        $store->set(self::TOKEN_KEY, json_encode(new AhjoAuthToken('fresh', time() + 3600, '234')));
        return FALSE;
      })
      ->shouldBeCalledOnce();

    $sut = $this->getSut($settings, keyValueFactory: $keyValueFactory, lock: $lock->reveal());

    $this->assertEquals('fresh', $sut->getAuthToken());
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

  /**
   * Gets the SUT.
   *
   * @param \Drupal\paatokset_ahjo_api\AhjoOpenId\Settings $settings
   *   Ahjo open id settings.
   * @param \GuzzleHttp\ClientInterface|null $httpClient
   *   The http client.
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface|null $keyValueFactory
   *   The key value factory.
   * @param \Drupal\Core\Lock\LockBackendInterface|null $lock
   *   The lock backend.
   */
  private function getSut(
    Settings $settings,
    ?ClientInterface $httpClient = NULL,
    ?KeyValueFactoryInterface $keyValueFactory = NULL,
    ?LockBackendInterface $lock = NULL,
  ) : AhjoOpenId {
    if (!$httpClient) {
      $httpClient = $this->prophesize(ClientInterface::class)->reveal();
    }
    $keyValueFactory ??= new KeyValueMemoryFactory();

    if (!$lock) {
      $lockProphecy = $this->prophesize(LockBackendInterface::class);
      $lockProphecy->acquire(Argument::cetera())->willReturn(TRUE);
      $lockProphecy->release(Argument::any())->willReturn(NULL);
      $lockProphecy->wait(Argument::cetera())->willReturn(FALSE);
      $lock = $lockProphecy->reveal();
    }

    $environmentResolver = $this->getEnvironmentResolver(Project::PAATOKSET, EnvironmentEnum::Test);

    return new AhjoOpenId($settings, $httpClient, $keyValueFactory, $lock, $environmentResolver);
  }

  /**
   * Gets a key value factory with the given token stored.
   */
  private function getSeededKeyValueFactory(AhjoAuthToken $token) : KeyValueFactoryInterface {
    $keyValueFactory = new KeyValueMemoryFactory();
    $keyValueFactory
      ->get(AhjoOpenId::TOKEN_COLLECTION)
      ->set(self::TOKEN_KEY, json_encode($token));

    return $keyValueFactory;
  }

}
