<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\AhjoOpenId;

use Drupal\Core\Form\FormState;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Drupal\helfi_tunnistamo\ReauthenticatorInterface;
use Drupal\helfi_tunnistamo\TokenManagerInterface;
use Drupal\paatokset_ahjo_api\AhjoOpenId\AhjoOpenId;
use Drupal\paatokset_ahjo_api\AhjoOpenId\AhjoProxyOpenIdClient;
use Drupal\paatokset_ahjo_api\AhjoOpenId\Controller\AhjoOpenIdController;
use Drupal\paatokset_ahjo_api\AhjoOpenId\DTO\AhjoAuthToken;
use Drupal\paatokset_ahjo_api\AhjoOpenId\Form\AhjoProxyAuthForm;
use Drupal\paatokset_ahjo_api\AhjoOpenId\Settings;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\paatokset_ahjo_api\Kernel\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use GuzzleHttp\Psr7\Response;

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

  /**
   * Tests Ahjo proxy OpenID flow.
   */
  public function testProxyFlow(): void {
    $this->installEntitySchema('path_alias');

    $this->config('paatokset_ahjo_api.settings')
      ->set('proxy_v3_url', 'http://ahjo-proxy:8080')
      ->set('proxy_v3_api_key', 'test-api-key')
      ->save();

    $ahjoOpenId = $this->prophesize(AhjoOpenId::class);
    $ahjoOpenId->checkAuthToken()->willReturn(FALSE);
    $this->container->set(AhjoOpenId::class, $ahjoOpenId->reveal());
    $this->container->set(Settings::class, new Settings('auth', 'token', 'callback', 'id', 'scope', 'secret'));

    $tokenManager = $this->prophesize(TokenManagerInterface::class);
    $tokenManager->hasSession()->willReturn(TRUE);
    $tokenManager->getAccessToken()->willReturn('user-token');

    $history = [];
    $this->container->set(AhjoProxyOpenIdClient::class, new AhjoProxyOpenIdClient(
      $this->createMockHistoryMiddlewareHttpClient($history, [
        new Response(200, body: '{"expires": null}'),
        new Response(302, ['Location' => 'https://ahjo.test/authorize?state=signed']),
        new Response(200, body: '{"expires": 1234}'),
      ]),
      $this->container->get('config.factory'),
      $tokenManager->reveal(),
    ));

    $controller = AhjoOpenIdController::create($this->container);

    $build = $controller->index();
    $this->assertStringContainsString('Token has not been set', (string) $build['proxy']['token']['#message_list'][array_key_first($build['proxy']['token']['#message_list'])][0]);
    $this->assertArrayHasKey('submit', $build['proxy']['form']['actions']);

    // Submitting the form starts the flow.
    $formState = new FormState();
    $this->container->get('form_builder')->submitForm(AhjoProxyAuthForm::class, $formState);
    $response = $formState->getResponse();
    $this->assertInstanceOf(TrustedRedirectResponse::class, $response);
    $this->assertSame('https://ahjo.test/authorize?state=signed', $response->getTargetUrl());

    foreach (['GET', 'POST'] as $i => $method) {
      /** @var \Psr\Http\Message\RequestInterface $proxyRequest */
      $proxyRequest = $history[$i]['request'];
      $this->assertSame($method, $proxyRequest->getMethod());
      $this->assertSame('http://ahjo-proxy:8080/openid', (string) $proxyRequest->getUri());
      $this->assertSame('test-api-key', $proxyRequest->getHeaderLine('api-key'));
      $this->assertSame($method === 'POST' ? 'user-token' : '', $proxyRequest->getHeaderLine('X-Access-Token'));
    }

    // The proxy handles the callback, Drupal only shows the status.
    $build = $controller->index();
    $this->assertStringContainsString('Token expired', (string) $build['proxy']['token']['#message_list'][array_key_first($build['proxy']['token']['#message_list'])][0]);
  }

  /**
   * Tests Ahjo proxy error messages.
   */
  public function testProxyErrors(): void {
    $this->installEntitySchema('path_alias');

    $this->config('paatokset_ahjo_api.settings')
      ->set('proxy_v3_url', 'http://ahjo-proxy:8080')
      ->set('proxy_v3_api_key', 'test-api-key')
      ->save();

    $ahjoOpenId = $this->prophesize(AhjoOpenId::class);
    $ahjoOpenId->checkAuthToken()->willReturn(FALSE);
    $this->container->set(AhjoOpenId::class, $ahjoOpenId->reveal());
    $this->container->set(Settings::class, new Settings('auth', 'token', 'callback', 'id', 'scope', 'secret'));

    $setClient = function (bool $hasSession, ?string $accessToken, array $responses, bool $reauthenticationFails = FALSE): void {
      $tokenManager = $this->prophesize(TokenManagerInterface::class);
      $tokenManager->hasSession()->willReturn($hasSession);
      $tokenManager->getAccessToken()->willReturn($accessToken);

      $reauthenticator = $this->prophesize(ReauthenticatorInterface::class);
      if ($reauthenticationFails) {
        $reauthenticator->reauthenticate(NULL)->willThrow(new \LogicException('The Tunnistamo client is not enabled.'));
      }
      else {
        $reauthenticator->reauthenticate(NULL)->willReturn(new TrustedRedirectResponse('https://tunnistamo.test/authorize'));
      }

      $history = [];
      $this->container->set(AhjoProxyOpenIdClient::class, new AhjoProxyOpenIdClient(
        $this->createMockHistoryMiddlewareHttpClient($history, $responses),
        $this->container->get('config.factory'),
        $tokenManager->reveal(),
        $reauthenticator->reveal(),
      ));
    };

    $getStatus = function (array $responses, bool $hasSession = TRUE, ?string $accessToken = 'user-token') use ($setClient): array {
      $setClient($hasSession, $accessToken, $responses);
      return AhjoOpenIdController::create($this->container)->index()['proxy'];
    };

    $submitForm = function (?string $accessToken, array $responses, bool $reauthenticationFails = FALSE) use ($setClient): ?string {
      $setClient(TRUE, $accessToken, $responses, $reauthenticationFails);
      $formState = new FormState();
      if (!$accessToken) {
        $formState->setValue('op', 'Log in again');
      }
      $this->container->get('form_builder')->submitForm(AhjoProxyAuthForm::class, $formState);

      return $formState->getResponse()?->headers->get('Location');
    };

    $getMessage = function (string $type): string {
      $messages = $this->container->get('messenger')->deleteByType($type);
      return (string) end($messages);
    };

    $submit = function (?string $accessToken, array $responses) use ($submitForm, $getMessage): string {
      $this->assertNull($submitForm($accessToken, $responses));
      return $getMessage(MessengerInterface::TYPE_ERROR);
    };

    // User did not log in with Helsinki AD. The status is shown, but the flow
    // cannot be started.
    $form = $getStatus([new Response(200, body: '{"expires": null}')], hasSession: FALSE);
    $this->assertStringContainsString('Token has not been set', (string) $form['token']['#message_list'][array_key_first($form['token']['#message_list'])][0]);
    $this->assertStringContainsString('requires a Helsinki AD login', (string) $form['form']['status']['#message_list'][array_key_first($form['form']['status']['#message_list'])][0]);
    $this->assertArrayNotHasKey('actions', $form['form']);

    // Proxy is down. The flow can still be started.
    $form = $getStatus([new Response(503)]);
    $this->assertStringContainsString('Ahjo proxy is unavailable', (string) $form['token']['#message_list'][array_key_first($form['token']['#message_list'])][0]);
    $this->assertArrayHasKey('submit', $form['form']['actions']);

    // Helsinki AD token has expired and could not be renewed. The form offers
    // to log in again instead of starting the flow.
    $form = $getStatus([new Response(200, body: '{"expires": null}')], accessToken: NULL);
    $this->assertStringContainsString('Your Helsinki AD login has expired', (string) $form['form']['status']['#message_list'][array_key_first($form['form']['status']['#message_list'])][0]);
    $this->assertArrayHasKey('reauthenticate', $form['form']['actions']);
    $this->assertArrayNotHasKey('submit', $form['form']['actions']);

    // The button sends the user to log in again.
    $this->assertSame('https://tunnistamo.test/authorize', $submitForm(NULL, []));

    // The user can't be logged in again.
    $this->assertNull($submitForm(NULL, [], reauthenticationFails: TRUE));
    $this->assertStringContainsString('Could not log in to Helsinki AD again', $getMessage(MessengerInterface::TYPE_ERROR));

    // Proxy rejects the access token.
    $this->assertStringContainsString('Ahjo proxy did not accept your Helsinki AD login', $submit('user-token', [
      new Response(401, body: '{"error": "Unauthorized."}'),
    ]));

    // Proxy fails to create the auth URL.
    $this->assertStringContainsString('Ahjo proxy is unavailable', $submit('user-token', [
      new Response(500, body: '{"error": "Missing configuration."}'),
    ]));
  }

}
