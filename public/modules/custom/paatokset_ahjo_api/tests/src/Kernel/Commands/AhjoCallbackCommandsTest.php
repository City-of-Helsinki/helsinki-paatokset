<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\Commands;

use Drupal\Core\Database\Connection;
use Drupal\Core\Queue\QueueFactory;
use Drupal\KernelTests\KernelTestBase;
use Drupal\paatokset_ahjo_api\Drush\Commands\AhjoCallbackCommands;
use Prophecy\Argument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Loader\Configurator\Traits\PropertyTrait;

/**
 * Tests ahjo callback commands.
 */
class AhjoCallbackCommandsTest extends KernelTestBase {

  use PropertyTrait;

  /**
   * {@inheritDoc}
   */
  protected static $modules = [
    'paatokset_ahjo_api',
  ];

  /**
   * Tests list commands.
   */
  public function testDeleteCommands(): void {
    $output = $this->prophesize(OutputInterface::class);
    $input = $this->prophesize(InputInterface::class);
    $style = $this->prophesize(SymfonyStyle::class);
    $style->confirm(Argument::any())->willReturn(TRUE);

    $queueFactory = $this->container->get(QueueFactory::class);
    $sut = new AhjoCallbackCommands(
      $this->container->get(QueueFactory::class),
      $this->container->get('logger.channel.paatokset_ahjo_api'),
      $this->container->get(Connection::class),
    );

    $sut->restoreState($input->reveal(), $output->reveal(), $style->reveal());

    /** @var \Drupal\Core\Queue\QueueInterface $queue */
    $queue = $queueFactory->get('ahjo_api_subscriber_queue');
    $id = $queue->createItem([
      'id' => 'test-callback',
    ]);
    $sut->deleteCallbackItem($id);
    $this->assertFalse($queue->claimItem());
    $queue->createItem([
      'id' => 'test-callback',
    ]);
    $sut->clearCallbackQueue();
    $this->assertFalse($queue->claimItem());

    /** @var \Drupal\Core\Queue\QueueInterface $queue */
    $queue = $queueFactory->get('ahjo_api_subscriber_queue');
    $id = $queue->createItem([
      'id' => 'test-callback',
    ]);
    $sut->deleteCallbackItem($id);
    $this->assertFalse($queue->claimItem());
    $queue->createItem([
      'id' => 'test-callback',
    ]);
    $sut->clearCallbackQueue();
    $this->assertFalse($queue->claimItem());

    /** @var \Drupal\Core\Queue\QueueInterface $queue */
    $queue = $queueFactory->get('ahjo_api_retry_queue');
    $id = $queue->createItem([
      'id' => 'test-callback',
    ]);
    $sut->deleteRetryItem($id);
    $this->assertFalse($queue->claimItem());
    $queue->createItem([
      'id' => 'test-callback',
    ]);
    $sut->clearRetryQueue();
    $this->assertFalse($queue->claimItem());

    /** @var \Drupal\Core\Queue\QueueInterface $queue */
    $queue = $queueFactory->get('ahjo_api_error_queue');
    $id = $queue->createItem([
      'id' => 'test-callback',
    ]);
    $sut->deleteErrorItem($id);
    $this->assertFalse($queue->claimItem());
    $queue->createItem([
      'id' => 'test-callback',
    ]);
    $sut->clearErrorQueue();
    $this->assertFalse($queue->claimItem());
  }

}
