<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\Commands;

use Drupal\paatokset_ahjo_api\AhjoOpenId\AhjoOpenId;
use Drupal\paatokset_ahjo_api\AhjoOpenId\AhjoOpenIdException;
use Drupal\paatokset_ahjo_api\AhjoOpenId\DTO\AhjoAuthToken;
use Drupal\paatokset_ahjo_api\Drush\Commands\AhjoTokenCommands;
use Drupal\Tests\paatokset_ahjo_api\Kernel\KernelTestBase;
use Prophecy\Argument;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests ahjo token commands.
 */
class AhjoTokenCommandsTest extends KernelTestBase {

  /**
   * Tests token refresh command.
   */
  public function testTokenRefreshCommand(): void {
    $openId = $this->prophesize(AhjoOpenId::class);
    $logger = $this->prophesize(LoggerInterface::class);
    $sut = new AhjoTokenCommands($openId->reveal(), $logger->reveal());

    // Should refresh the token.
    $openId->refreshAuthToken()
      ->shouldBeCalled()
      ->willReturn(new AhjoAuthToken('123', time() + 3600, '456'));

    $tester = new CommandTester($sut);
    $tester->execute([]);
    $this->assertEquals(Command::SUCCESS, $tester->getStatusCode());
  }

  /**
   * Tests token refresh command failure.
   */
  public function testTokenRefreshCommandFailure(): void {
    $openId = $this->prophesize(AhjoOpenId::class);
    $logger = $this->prophesize(LoggerInterface::class);
    $sut = new AhjoTokenCommands($openId->reveal(), $logger->reveal());

    // Should log an error message.
    $logger->log("error", Argument::any(), Argument::any())->shouldBeCalled();

    // Should refresh the token.
    $openId->refreshAuthToken()
      ->willThrow(new AhjoOpenIdException('test exception'));

    $tester = new CommandTester($sut);
    $tester->execute([]);
    $this->assertEquals(Command::FAILURE, $tester->getStatusCode());
  }

}
