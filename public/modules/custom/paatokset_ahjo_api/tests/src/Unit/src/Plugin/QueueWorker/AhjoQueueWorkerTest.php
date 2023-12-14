<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Unit;

use Drupal\ahjo_queue_worker_test\Plugin\QueueWorker\DummyWorker;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\paatokset_ahjo_proxy\AhjoProxy;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;

/**
 * @coversDefaultClass \Drupal\paatokset_ahjo_api\AhjoQueueWorkerBase
 * @group paatokset_ahjo_api
 */
class AhjoQueueWorkerTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * Gets the SUT.
   *
   * @param \Drupal\paatokset_ahjo_proxy\AhjoProxy $ahjoProxy
   *   The ahjo proxy.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   The logger channel factory.
   *
   * @return \Drupal\ahjo_queue_worker_test\Plugin\QueueWorker\DummyWorker
   *   Ahjo queue worker SUT.
   */
  private function getSut(AhjoProxy $ahjoProxy, LoggerChannelFactoryInterface $loggerChannelFactory = NULL): DummyWorker {
    if (is_null($loggerChannelFactory)) {
      $logger = $this->prophesize(LoggerChannelInterface::class);
      $loggerChannelFactory = $this->prophesize(LoggerChannelFactoryInterface::class);
      $loggerChannelFactory
        ->get(Argument::type('string'))
        ->willReturn($logger->reveal());
      $loggerChannelFactory = $loggerChannelFactory->reveal();
    }

    $container = new ContainerBuilder();
    $container->set('paatokset_ahjo_proxy', $ahjoProxy);
    $container->set('logger.factory', $loggerChannelFactory);

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
   * Test that derived class can override logger channel.
   *
   * @covers ::create
   */
  public function testDerivedClassLoggerChannel(): void {
    $ahjoProxy = $this->prophesizeAhjoProxy(-1, FALSE);
    $logger = $this->prophesize(LoggerChannelInterface::class);
    $loggerChannelFactory = $this->prophesize(LoggerChannelFactoryInterface::class);

    $loggerChannelFactory
      // Dummy class override is used.
      ->get(DummyWorker::LOGGER_CHANNEL)
      ->shouldBeCalled()
      ->willReturn($logger->reveal());

    $this->getSut($ahjoProxy->reveal(), $loggerChannelFactory->reveal());
  }

  /**
   * Test that queue is suspended when ahjo proxy is not operational.
   *
   * @covers ::create
   * @covers ::processItem
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
   *
   * @covers ::create
   * @covers ::processItem
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
   *
   * @covers ::create
   * @covers ::processItem
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
   *
   * @covers ::create
   * @covers ::processItem
   * @covers ::moveToErrorQueue
   * @covers ::getMaxRetryTime
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
   *
   * @covers ::create
   * @covers ::processItem
   * @covers ::moveToErrorQueue
   * @covers ::getMaxRetryTime
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
   *
   * @covers ::create
   * @covers ::processItem
   * @covers ::moveToErrorQueue
   * @covers ::getFallbackQueueId
   * @covers ::getMaxRetryTime
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
   *
   * @covers ::create
   * @covers ::processItem
   * @covers ::moveToErrorQueue
   * @covers ::getMaxRetryTime
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
   *
   * @covers ::create
   * @covers ::processItem
   * @covers ::moveToErrorQueue
   * @covers ::getMaxRetryTime
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
