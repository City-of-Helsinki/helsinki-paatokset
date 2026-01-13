<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\Commands;

use Drupal\KernelTests\KernelTestBase;
use Drupal\paatokset_ahjo_api\AhjoOpenId\AhjoOpenId;
use Drupal\paatokset_ahjo_api\AhjoOpenId\AhjoOpenIdException;
use Drupal\paatokset_ahjo_api\AhjoOpenId\DTO\AhjoAuthToken;
use Drupal\paatokset_ahjo_api\Drush\Commands\AhjoTokenCommands;
use Prophecy\Argument;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\Traits\PropertyTrait;

/**
 * Tests ahjo token commands.
 */
class AhjoTokenCommandsTest extends KernelTestBase {

  use PropertyTrait;

  /**
   * {@inheritDoc}
   */
  protected static $modules = [
    'paatokset_ahjo_api',
    'helfi_api_base',
    'path_alias',
    'pathauto',
    'token',
    'migrate',
  ];

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
    $this->assertEquals(AhjoTokenCommands::EXIT_SUCCESS, $sut->refreshAuthToken());
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

    $logger = $this->prophesize(LoggerInterface::class);
    $sut->setLogger($logger->reveal());

    // Should refresh the token.
    $openId->refreshAuthToken()
      ->willThrow(new AhjoOpenIdException('test exception'));
    $this->assertEquals(AhjoTokenCommands::EXIT_FAILURE, $sut->refreshAuthToken());
  }

}
