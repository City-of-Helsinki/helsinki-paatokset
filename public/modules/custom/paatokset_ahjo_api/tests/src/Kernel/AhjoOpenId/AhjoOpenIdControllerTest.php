<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\AhjoOpenId;

use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Drupal\paatokset_ahjo_api\AhjoOpenId\AhjoOpenId;
use Drupal\paatokset_ahjo_api\AhjoOpenId\Controller\AhjoOpenIdController;
use Drupal\paatokset_ahjo_api\AhjoOpenId\DTO\AhjoAuthToken;
use Drupal\paatokset_ahjo_api\AhjoOpenId\Form\AhjoProxyAuthForm;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\paatokset_ahjo_api\Kernel\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

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
    'file',
    'externalauth',
    'openid_connect',
    'helfi_tunnistamo',
  ];

  /**
   * The session the OpenID Connect tokens live in.
   */
  private Session $session;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->session = new Session(new MockArraySessionStorage());
    $this->container->set('session', $this->session);
    $this->container->get('request_stack')->getCurrentRequest()->setSession($this->session);
  }

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

    $this->setSessionTokens('user-token');

    $history = [];
    $this->container->set('http_client', $this->createMockHistoryMiddlewareHttpClient($history, [
      new Response(200, body: '{"expires": null}'),
      new Response(302, ['Location' => 'https://ahjo.test/authorize?state=signed']),
      new Response(200, body: '{"expires": 1234}'),
    ]));

    $controller = AhjoOpenIdController::create($this->container);

    $build = $controller->index();
    $this->assertStringContainsString('Token has not been set', (string) $build['proxy']['token']['#message_list'][array_key_first($build['proxy']['token']['#message_list'])][0]);
    $this->assertArrayHasKey('submit', $build['proxy']['form']['actions']);

    // Submitting the form starts the flow.
    $formState = new FormState();
    $this->container->get(FormBuilderInterface::class)->submitForm(AhjoProxyAuthForm::class, $formState);
    $response = $formState->getResponse();
    $this->assertInstanceOf(TrustedRedirectResponse::class, $response);
    $this->assertSame('https://ahjo.test/authorize?state=signed', $response->getTargetUrl());

    foreach (['GET', 'POST'] as $i => $method) {
      /** @var \Psr\Http\Message\RequestInterface $proxyRequest */
      $proxyRequest = $history[$i]['request'];
      $this->assertSame($method, $proxyRequest->getMethod());
      $this->assertSame('http://ahjo-proxy:8080/openid', (string) $proxyRequest->getUri());
      $this->assertSame('test-api-key', $proxyRequest->getHeaderLine('api-key'));

      if ($method === 'POST') {
        $this->assertSame('user-token', $proxyRequest->getHeaderLine('X-Access-Token'));
      }
    }

    $build = $controller->index();
    $this->assertStringContainsString('Token expired', (string) $build['proxy']['token']['#message_list'][array_key_first($build['proxy']['token']['#message_list'])][0]);
  }

  /**
   * Sets the OpenID Connect tokens of the current session.
   *
   * @param string|null $accessToken
   *   The access token, or NULL if the user did not log in with Tunnistamo.
   * @param bool $expired
   *   Whether the access token has expired.
   */
  private function setSessionTokens(?string $accessToken, bool $expired = FALSE): void {
    $this->session->clear();
    if ($accessToken) {
      $this->session->set('openid_connect_access', $accessToken);
      $this->session->set('openid_connect_expire', $this->container->get('datetime.time')->getCurrentTime() + ($expired ? -10 : 3600));
    }
  }

}
