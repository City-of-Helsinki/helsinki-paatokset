<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Unit;

use Drupal\ahjo_queue_worker_test\Plugin\QueueWorker\DummyWorker;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\paatokset_ahjo_proxy\AhjoProxy;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
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
class AhjoQueueWorkerTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * Gets the SUT.
   *
   * @param \Drupal\paatokset_ahjo_proxy\AhjoProxy $ahjoProxy
   *   The ahjo proxy.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger.
   *
   * @return \Drupal\ahjo_queue_worker_test\Plugin\QueueWorker\DummyWorker
   *   Ahjo queue worker SUT.
   */
  private function getSut(AhjoProxy $ahjoProxy, ?LoggerChannelInterface $logger = NULL): DummyWorker {
    if (is_null($logger)) {
      $logger = $this->prophesize(LoggerChannelInterface::class);
      $logger = $logger->reveal();
    }

    $container = new ContainerBuilder();
    $container->set('paatokset_ahjo_proxy', $ahjoProxy);
    $container->set('logger.channel.paatokset_ahjo_api', $logger);

    return DummyWorker::create($container, [], 'ahjo_queue_worker_test', []);
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

    return $ahjoProxy;
  }

  /**
   * Test that queue is suspended when ahjo proxy is not operational.
   */
  public function testAhjoProxyNotOperational(): void {
    $ahjoProxy = $this->prophesizeAhjoProxy(-1, FALSE);

    $this->expectException(SuspendQueueException::class);
    $this->getSut($ahjoProxy->reveal())->processItem([
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

    $this->getSut($ahjoProxy->reveal())->processItem([
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

    $this->getSut($ahjoProxy->reveal())->processItem([
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

    $this->expectException(\Exception::class);

    $sut = $this->getSut($ahjoProxy->reveal());
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

    $sut = $this->getSut($ahjoProxy->reveal());
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

    $sut = $this->getSut($ahjoProxy->reveal());
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

    $this->getSut($ahjoProxy->reveal())->processItem([
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

    $this->expectException(\Exception::class);

    $sut = $this->getSut($ahjoProxy->reveal());
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

}
