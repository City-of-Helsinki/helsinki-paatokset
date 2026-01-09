<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\paatokset_ahjo_api\Controller\AhjoSubscriberController;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests Ahjo callbacks.
 *
 * Meeting lifecyle part 1: ahjo callback.
 */
#[Group('paatokset_ahjo_api')]
class CallbackTest extends AhjoKernelTestBase {

  use ApiTestTrait;
  use UserCreationTrait;
  use NodeCreationTrait;
  use ProphecyTrait;

  /**
   * {@inheritDoc}
   */
  public function setUp(): void {
    parent::setUp();

    putenv('AHJO_PROXY_BASE_URL=https://paatokset.hel.fi/');
  }

  /**
   * Tests ahjo callback with anonymous permissions.
   */
  public function testPermissions(): void {
    $route = Url::fromRoute('paatokset_ahjo_api.subscriber', ['id' => '123']);
    $request = $this->getMockedRequest($route->toString(), 'POST', [], [
      'hello' => 'world',
    ]);

    $this->assertNotTrue($this->processRequest($request)->isOk());
  }

  /**
   * Tests ahjo callback.
   *
   * Ahjo makes callbacks to paatokset when relevant data is changed.
   * Callback should:
   *  - Add new item to queue.
   *  - Invalidate caches so next api requests fetch fresh data.
   */
  public function testCallback(): void {
    // List of cache keys that were cleared during callback.
    $cleared_keys = [];

    $cache = $this->prophesize(CacheBackendInterface::class);
    $cache->invalidate(Argument::type('string'))->will(function ($args) use (&$cleared_keys) {
      $cleared_keys[] = $args[0];
    });

    $this->container->set('cache.default', $cache->reveal());

    $this->setUpCurrentUser(permissions: ['access content']);
    $this->createNode([
      'type' => 'meeting',
      'status' => NodeInterface::PUBLISHED,
      'field_meeting_id' => '123',
      'field_meeting_agenda_published' => 1,
      'field_meeting_minutes_published' => 0,
      'field_meeting_agenda' => [
        '{"PDF": {"NativeId": "456"}}',
      ],
    ]);

    $response = $this->makeCallback('meetings', [
      'id' => '123',
    ]);

    $this->assertTrue($response->isOk());
    $content = json_decode($response->getContent(), TRUE);
    $this->assertNotEmpty($content['item_id']);

    $count = $this->container->get('database')
      ->select('queue', 'q')
      ->condition('q.item_id', $content['item_id'])
      ->condition('q.name', AhjoSubscriberController::QUEUE_NAME)
      ->countQuery()
      ->execute()
      ->fetchField();

    // An item was added to the queue.
    $this->assertEquals(1, $count);

    // All listed keys were cleared.
    $this->assertTrue(!array_diff($cleared_keys, [
      // API URLs.
      "ahjo-proxy-https_ahjo_hel_fi_9802_ahjorest_v1_meetings_123",
      "ahjo-proxy-https_ahjo_hel_fi_9802_ahjorest_v1_meetings_123_",
      // Proxy URLs.
      "ahjo-proxy-https_paatokset_hel_fi_ahjo_proxy_meetings_123",
      "ahjo-proxy-https_paatokset_hel_fi_ahjo_proxy_meetings_single_123",
      // API agenda item URL.
      "ahjo-proxy-https_ahjo_hel_fi_9802_ahjorest_v1_meetings_123_agendaitems_456",
      // Proxy agenda item URL.
      "ahjo-proxy-https_paatokset_hel_fi_ahjo_proxy_agenda_item_123_456",
    ]));
  }

  /**
   * Mocks a request to the ahjo callback route.
   *
   * @param string $type
   *   Subscriber callback ID (decisions, meetings, etc.).
   * @param array $body
   *   Request body.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response.
   */
  private function makeCallback(string $type, array $body): Response {
    $route = Url::fromRoute('paatokset_ahjo_api.subscriber', ['id' => $type]);
    $request = $this->getMockedRequest($route->toString(), 'POST', [], $body);
    return $this->processRequest($request);
  }

}
