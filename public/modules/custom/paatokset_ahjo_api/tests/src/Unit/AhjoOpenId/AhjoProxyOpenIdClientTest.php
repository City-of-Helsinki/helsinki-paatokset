<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Unit\AhjoOpenId;

use Drupal\Core\Url;
use Drupal\helfi_tunnistamo\ReauthenticatorInterface;
use Drupal\helfi_tunnistamo\TokenManagerInterface;
use Drupal\paatokset_ahjo_api\AhjoOpenId\AhjoOpenIdException;
use Drupal\paatokset_ahjo_api\AhjoOpenId\AhjoProxyOpenIdClient;
use Drupal\paatokset_ahjo_api\AhjoOpenId\AhjoProxySessionExpiredException;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Group;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Tests the Ahjo proxy OpenID client.
 */
#[Group('paatokset_ahjo_api')]
class AhjoProxyOpenIdClientTest extends UnitTestCase {

  use ApiTestTrait;
  use ProphecyTrait;

  /**
   * Gets the SUT.
   *
   * @param array<mixed> $history
   *   Request history.
   * @param \GuzzleHttp\Psr7\Response[] $responses
   *   Responses.
   * @param string|null $accessToken
   *   Access token of the current user.
   * @param bool $tunnistamo
   *   Whether helfi_tunnistamo is available.
   * @param string|null $url
   *   Configured proxy URL.
   * @param \Drupal\helfi_tunnistamo\ReauthenticatorInterface|null $reauthenticator
   *   The reauthenticator.
   */
  private function getSut(array &$history, array $responses = [], ?string $accessToken = 'user-token', bool $tunnistamo = TRUE, ?string $url = 'http://ahjo-proxy:8080/', ?ReauthenticatorInterface $reauthenticator = NULL): AhjoProxyOpenIdClient {
    $tokenManager = $this->prophesize(TokenManagerInterface::class);
    $tokenManager->getAccessToken()->willReturn($accessToken);
    $tokenManager->hasSession()->willReturn($accessToken !== NULL);

    return new AhjoProxyOpenIdClient(
      $this->createMockHistoryMiddlewareHttpClient($history, $responses),
      $this->getConfigFactoryStub([
        'paatokset_ahjo_api.settings' => [
          'proxy_v3_url' => $url,
          'proxy_v3_api_key' => 'test-api-key',
        ],
      ]),
      $tunnistamo ? $tokenManager->reveal() : NULL,
      $tunnistamo ? $reauthenticator : NULL,
    );
  }

  /**
   * Tests successful requests.
   */
  public function testRequests(): void {
    $history = [];
    $sut = $this->getSut($history, [
      new Response(200, body: '{"expires": null}'),
      new Response(200, body: '{"expires": 1234}'),
      new Response(302, ['Location' => 'https://ahjo.test/authorize?state=abc']),
    ]);

    $this->assertTrue($sut->isConfigured());
    $this->assertTrue($sut->hasSession());
    $this->assertTrue($sut->hasValidSession());

    $status = $sut->getTokenStatus();
    $this->assertFalse($status->hasToken());
    $this->assertFalse($status->isExpired());
    $status = $sut->getTokenStatus();
    $this->assertTrue($status->hasToken());
    $this->assertTrue($status->isExpired());
    $this->assertSame(1234, $status->expires);
    $this->assertSame('https://ahjo.test/authorize?state=abc', $sut->startAuthFlow());

    $this->assertCount(3, $history);

    foreach (['GET', 'GET', 'POST'] as $i => $method) {
      /** @var \Psr\Http\Message\RequestInterface $request */
      $request = $history[$i]['request'];
      $this->assertSame($method, $request->getMethod());
      $this->assertSame('http://ahjo-proxy:8080/openid', (string) $request->getUri());
      $this->assertSame('test-api-key', $request->getHeaderLine('api-key'));
      // Only starting the flow requires the access token.
      $this->assertSame($method === 'POST' ? 'user-token' : '', $request->getHeaderLine('X-Access-Token'));
    }
  }

  /**
   * Tests errors.
   */
  public function testErrors(): void {
    $history = [];

    // Missing helsinki-tunnistus session.
    $sut = $this->getSut($history, accessToken: NULL);
    $this->assertFalse($sut->hasSession());
    $this->assertFalse($sut->hasValidSession());
    $this->assertException(fn () => $sut->startAuthFlow(), 'Helsinki AD session', AhjoProxySessionExpiredException::class);

    // helfi_tunnistamo is not available.
    $sut = $this->getSut($history, tunnistamo: FALSE);
    $this->assertFalse($sut->hasSession());
    $this->assertFalse($sut->hasValidSession());
    $this->assertException(fn () => $sut->startAuthFlow(), 'Helsinki AD session', AhjoProxySessionExpiredException::class);
    $this->assertException(fn () => $sut->reauthenticate(Url::fromUri('internal:/some/path')), 'helfi_tunnistamo is not available');

    $this->assertEmpty($history);

    // Proxy errors.
    $sut = $this->getSut($history, [
      new Response(401, body: '{"error": "Unauthorized."}'),
      new Response(200, body: 'not json'),
      new Response(200, body: '{"expires": "tomorrow"}'),
      new Response(401, body: '{"error": "Unauthorized."}'),
      new Response(500, body: '{"error": "Missing configuration."}'),
      new Response(200, body: '{}'),
      new Response(302, ['Location' => 'http://ahjo.test/authorize']),
    ]);
    $this->assertException(fn () => $sut->getTokenStatus(), 'Ahjo proxy request failed', code: 401);
    $this->assertException(fn () => $sut->getTokenStatus(), 'Invalid JSON', code: 0);
    // Invalid expiry is treated as no token.
    $this->assertFalse($sut->getTokenStatus()->hasToken());
    $this->assertException(fn () => $sut->startAuthFlow(), 'Ahjo proxy request failed', code: 401);
    $this->assertException(fn () => $sut->startAuthFlow(), 'Ahjo proxy request failed', code: 500);
    $this->assertException(fn () => $sut->startAuthFlow(), 'did not return an auth URL', code: 200);
    $this->assertException(fn () => $sut->startAuthFlow(), 'did not return an auth URL', code: 302);

    // Not configured.
    $sut = $this->getSut($history, url: NULL);
    $this->assertFalse($sut->isConfigured());
    $this->assertException(fn () => $sut->getTokenStatus(), 'not configured');
  }

  /**
   * Tests reauthentication.
   */
  public function testReauthenticate(): void {
    $history = [];
    $destination = Url::fromUri('internal:/some/path');

    $reauthenticator = $this->prophesize(ReauthenticatorInterface::class);
    $reauthenticator->reauthenticate($destination)->willReturn(new RedirectResponse('https://tunnistamo.test/authorize'));
    $response = $this->getSut($history, reauthenticator: $reauthenticator->reveal())->reauthenticate($destination);
    $this->assertSame('https://tunnistamo.test/authorize', $response->headers->get('Location'));

    $reauthenticator = $this->prophesize(ReauthenticatorInterface::class);
    $reauthenticator->reauthenticate($destination)->willThrow(new \LogicException('The Tunnistamo client is not enabled.'));
    $sut = $this->getSut($history, reauthenticator: $reauthenticator->reveal());
    $this->assertException(fn () => $sut->reauthenticate($destination), 'client is not enabled');
    $this->assertEmpty($history);
  }

  /**
   * Asserts that the callback throws AhjoOpenIdException.
   *
   * @param callable $callback
   *   The callback.
   * @param string $message
   *   Expected message.
   * @param class-string<\Drupal\paatokset_ahjo_api\AhjoOpenId\AhjoOpenIdException> $class
   *   Expected exception class.
   * @param int|null $code
   *   Expected exception code.
   */
  private function assertException(callable $callback, string $message, string $class = AhjoOpenIdException::class, ?int $code = NULL): void {
    try {
      $callback();
      $this->fail('Expected exception');
    }
    catch (AhjoOpenIdException $e) {
      $this->assertInstanceOf($class, $e);
      $this->assertStringContainsString($message, $e->getMessage());
      if ($code !== NULL) {
        $this->assertSame($code, $e->getCode());
      }
    }
  }

}
