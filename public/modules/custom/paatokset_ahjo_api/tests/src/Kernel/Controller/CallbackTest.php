<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\Controller;

use Drupal\Core\Url;
use Drupal\paatokset_ahjo_api\Plugin\QueueWorker\AhjoCallbackQueueWorker;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\paatokset_ahjo_api\Kernel\AhjoEntityKernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests Ahjo callbacks.
 *
 * Meeting lifecyle part 1: ahjo callback.
 */
#[Group('paatokset_ahjo_api')]
#[RunTestsInSeparateProcesses]
class CallbackTest extends AhjoEntityKernelTestBase {

  use ApiTestTrait;
  use UserCreationTrait;

  /**
   * {@inheritDoc}
   */
  protected static $modules = [
    'paatokset_ahjo_proxy',
  ];

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
   * Ahjo makes callbacks to paatokset when relevant data is changed. The
   * callback should add a new item to the queue. Cache invalidation happens
   * later, when the queue item is processed; that is covered by
   * \Drupal\Tests\paatokset_ahjo_api\Kernel\Queue\AhjoQueueWorkerCacheTest.
   */
  public function testCallback(): void {
    $this->setUpCurrentUser(permissions: ['access ahjo documents']);

    $response = $this->makeCallback('meetings', [
      'id' => '123',
    ]);

    $this->assertTrue($response->isOk());
    $content = json_decode($response->getContent(), TRUE);
    $this->assertNotEmpty($content['item_id']);

    $count = $this->container->get('database')
      ->select('queue', 'q')
      ->condition('q.item_id', $content['item_id'])
      ->condition('q.name', AhjoCallbackQueueWorker::QUEUE_NAME)
      ->countQuery()
      ->execute()
      ->fetchField();

    // An item was added to the queue.
    $this->assertEquals(1, $count);
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
