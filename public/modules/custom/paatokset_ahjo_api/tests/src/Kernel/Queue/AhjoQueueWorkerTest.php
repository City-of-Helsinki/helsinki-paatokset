<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\Queue;

use Drupal\ahjo_queue_worker_test\Plugin\QueueWorker\DummyWorker;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\node\NodeInterface;
use Drupal\paatokset_ahjo_proxy\AhjoProxy;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\paatokset_ahjo_api\Kernel\AhjoEntityKernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;

/**
 * Tests ahjo queues.
 *
 * Queues should:
 * - Run ahjo migration for single entity. Move item to error/failure queue if
 *   the migration fails.
 * - Migrations fail if the returned status code != 1.
 * - If processing meeting and if not retrying previously failed item
 *   (=feature or a bug?), set `field_agenda_items_processed` to false.
 */
#[Group('paatokset_ahjo_api')]
#[RunTestsInSeparateProcesses]
class AhjoQueueWorkerTest extends AhjoEntityKernelTestBase {

  use ApiTestTrait;
  use NodeCreationTrait;
  use ProphecyTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'paatokset_ahjo_proxy',
  ];

  /**
   * Test that queue is suspended when ahjo proxy is not operational.
   */
  public function testAhjoProxyNotOperational(): void {
    $ahjoProxy = $this->prophesizeAhjoProxy(-1, FALSE);
    $this->container->set('paatokset_ahjo_proxy', $ahjoProxy->reveal());

    $this->expectException(SuspendQueueException::class);
    $this->getSut()->processItem([
      'id' => 'test',
      'content' => (object) [
        'id' => '1',
        'updatetype' => 'Created',
      ],
    ]);
  }

  /**
   * Test that queue worker marks meeting motions to be regenerated.
   */
  public function testMeetingMotionUpdating(): void {
    $entityId = '123';
    $ahjoProxy = $this->prophesizeAhjoProxy(1);
    $ahjoProxy
      ->markMeetingMotionsAsUnprocessed(Argument::exact($entityId))
      ->shouldBeCalled();
    $this->container->set('paatokset_ahjo_proxy', $ahjoProxy->reveal());

    $this->getSut()->processItem([
      'id' => 'meetings',
      'content' => (object) [
        'id' => $entityId,
        'updatetype' => 'Updated',
      ],
    ]);
  }

  /**
   * Meeting motions should not be updated if we are processing moved items.
   *
   * Is this a bug or wanted behaviour?
   */
  public function testMeetingMotionMoved(): void {
    $ahjoProxy = $this->prophesizeAhjoProxy(1);
    $ahjoProxy
      ->markMeetingMotionsAsUnprocessed(Argument::any())
      ->shouldNotBeCalled();
    $this->container->set('paatokset_ahjo_proxy', $ahjoProxy->reveal());

    $this->getSut()->processItem([
      'id' => 'meetings',
      'content' => (object) [
        'id' => '123',
        'updatetype' => 'Updated - ahjo_api_retry_queue',
      ],
    ]);
  }

  /**
   * Test that an exception is thrown if the item is not expired.
   *
   * If an exception is thrown, Drupal will retry queue items on the next run.
   * Items are expired if they are created after the max retry time.
   *
   * @see QueueWorkerInterface::processItem
   */
  public function testRetryNonExpired(): void {
    $ahjoProxy = $this->prophesizeAhjoProxy(-1);
    $this->container->set('paatokset_ahjo_proxy', $ahjoProxy->reveal());

    $this->expectException(\Exception::class);

    $sut = $this->getSut();
    $sut->processItem([
      'id' => 'meeting',
      // Item is created after the max retry time:
      'created' => $sut->getMaxRetryTime() + 10,
      'content' => (object) [
        'id' => '123',
        'updatetype' => 'Updated',
      ],
    ]);
  }

  /**
   * Test that the item should not be moved if is already ih the queue.
   */
  public function testMoveItemAlreadyInQueue(): void {
    $ahjoProxy = $this->prophesizeAhjoProxy(-1);

    $ahjoProxy
      ->checkIfItemIsAlreadyInQueue(Argument::any(), Argument::any(), Argument::any())
      ->willReturn(TRUE);

    $ahjoProxy
      ->addItemToAhjoQueue(Argument::any(), Argument::any(), Argument::any(), Argument::any())
      ->shouldNotBeCalled();

    $this->container->set('paatokset_ahjo_proxy', $ahjoProxy->reveal());

    $sut = $this->getSut();
    $sut->processItem([
      'id' => 'meeting',
      // Item is created before the max retry time -> expired.
      'created' => $sut->getMaxRetryTime() - 10,
      'content' => (object) [
        'id' => '123',
        'updatetype' => 'Updated',
      ],
    ]);
  }

  /**
   * Test that the item is moved into another queue if it is expired.
   *
   * Only expired items are moved to the retry queue.
   */
  public function testMoveExpiredItem(): void {
    $entity_id = '123';
    $ahjoProxy = $this->prophesizeAhjoProxy(-1);

    $ahjoProxy
      ->checkIfItemIsAlreadyInQueue(Argument::any(), Argument::any(), Argument::any())
      ->willReturn(FALSE);

    $ahjoProxy
      ->addItemToAhjoQueue(Argument::any(), $entity_id, Argument::any(), 'Updated - ahjo_queue_worker_test')
      ->shouldBeCalled()
      ->willReturn(321);

    $this->container->set('paatokset_ahjo_proxy', $ahjoProxy->reveal());

    $sut = $this->getSut();
    $sut->processItem([
      'id' => 'meeting',
      // Item is created before the max retry time -> expired:
      'created' => $sut->getMaxRetryTime() - 10,
      'content' => (object) [
        'id' => $entity_id,
        'updatetype' => 'Updated',
      ],
    ]);
  }

  /**
   * Test that items are always moved if the created timestamp is missing.
   */
  public function testMoveNoCreatedField(): void {
    $ahjoProxy = $this->prophesizeAhjoProxy(-1);

    $ahjoProxy
      ->checkIfItemIsAlreadyInQueue(Argument::any(), Argument::any(), Argument::any())
      ->willReturn(FALSE);

    $ahjoProxy
      ->addItemToAhjoQueue(Argument::any(), Argument::any(), Argument::any(), Argument::any())
      ->shouldBeCalled()
      ->willReturn(321);

    $this->container->set('paatokset_ahjo_proxy', $ahjoProxy->reveal());

    $this->getSut()->processItem([
      'id' => 'meeting',
      'content' => (object) [
        'id' => '123',
        'updatetype' => 'Updated',
      ],
    ]);
  }

  /**
   * Test that fallback queue insert failure should throw an exception.
   */
  public function testFallbackInsertFailure(): void {
    $ahjoProxy = $this->prophesizeAhjoProxy(-1);

    $ahjoProxy
      ->checkIfItemIsAlreadyInQueue(Argument::any(), Argument::any(), Argument::any())
      ->willReturn(FALSE);

    $ahjoProxy
      ->addItemToAhjoQueue(Argument::any(), Argument::any(), Argument::any(), Argument::any())
      ->willReturn(NULL);

    $this->container->set('paatokset_ahjo_proxy', $ahjoProxy->reveal());

    $this->expectException(\Exception::class);

    $sut = $this->getSut();
    $sut->processItem([
      'id' => 'meeting',
      // Item is created before the max retry time -> expired:
      'created' => $sut->getMaxRetryTime() - 10,
      'content' => (object) [
        'id' => '123',
        'updatetype' => 'Updated',
      ],
    ]);
  }

  /**
   * Processing a meeting item invalidates its API and proxy cache keys.
   */
  public function testCacheInvalidation(): void {
    // The expected cache keys below are derived from this base URL.
    putenv('AHJO_PROXY_BASE_URL=https://paatokset.hel.fi/');

    // Capture every cache key that gets invalidated.
    $cleared_keys = [];
    $cache = $this->prophesize(CacheBackendInterface::class);
    $cache->invalidate(Argument::type('string'))->will(function ($args) use (&$cleared_keys) {
      $cleared_keys[] = $args[0];
    });
    // The proxy reads from the cache before fetching content; treat everything
    // as a cache miss.
    $cache->get(Argument::any())->willReturn(FALSE);
    $cache->set(Argument::cetera());
    $this->container->set('cache.default', $cache->reveal());

    // Run in proxy mode so isOperational() does not require an OpenID token,
    // and stub the proxy HTTP request so migrateSingleEntity() makes no real
    // network call. The migration "fails" (empty response), which is fine:
    // cache invalidation runs before the migration result is evaluated.
    putenv('SKIP_AUTH_HEADERS=1');
    $this->container->set('http_client', $this->createMockHttpClient([
      new GuzzleResponse(500),
    ]));

    // A published meeting is required to invalidate its agenda item caches.
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

    // Process a meeting item through the queue worker. The migration is
    // expected to fail in the test environment, so the worker throws to return
    // the item to the queue, but only after the caches have been invalidated.
    $sut = $this->getSut();
    try {
      $sut->processItem([
        'id' => 'meetings',
        'created' => $sut->getMaxRetryTime() + 10,
        'content' => (object) [
          'id' => '123',
          'updatetype' => 'Updated',
        ],
      ]);
    }
    catch (\Exception) {
      // Migration failure is expected; see comment above.
    }

    // Exactly the expected keys were cleared while processing the item.
    $expected_keys = [
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
    ];
    sort($cleared_keys);
    sort($expected_keys);
    $this->assertEquals($expected_keys, $cleared_keys);
  }

  /**
   * Mock ahjo proxy service.
   */
  private function prophesizeAhjoProxy(int $migrationReturnCode, bool $operational = TRUE): AhjoProxy|ObjectProphecy {
    $ahjoProxy = $this->prophesize(AhjoProxy::class);

    $ahjoProxy
      ->isOperational()
      ->willReturn($operational);

    $ahjoProxy
      ->migrateSingleEntity(Argument::any(), Argument::any())
      // Successful migration.
      ->willReturn($migrationReturnCode);

    // Cache invalidation was moved into the queue worker and runs for every
    // processed item. Both methods return void, so no return value is stubbed.
    $ahjoProxy->invalidateCacheForProxy(Argument::any(), Argument::any());
    $ahjoProxy->invalidateAgendaItemsCache(Argument::any());

    return $ahjoProxy;
  }

  /**
   * Gets the SUT.
   *
   * @return \Drupal\ahjo_queue_worker_test\Plugin\QueueWorker\DummyWorker
   *   Ahjo queue worker SUT.
   */
  private function getSut(): DummyWorker {
    return DummyWorker::create($this->container, [], 'ahjo_queue_worker_test', []);
  }

}
