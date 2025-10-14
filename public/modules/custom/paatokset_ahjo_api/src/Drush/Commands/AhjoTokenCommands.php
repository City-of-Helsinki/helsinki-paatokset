<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Drush\Commands;

use Consolidation\AnnotatedCommand\Attributes\Command;
use Drupal\Core\Utility\Error;
use Drupal\paatokset_ahjo_api\AhjoOpenId\AhjoOpenId;
use Drupal\paatokset_ahjo_api\AhjoOpenId\AhjoOpenIdException;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Ahjo token commands.
 *
 * @package Drupal\paatokset_ahjo_api\Drush\Commands
 */
final class AhjoTokenCommands extends DrushCommands {

  use AutowireTrait;

  public function __construct(
    private readonly AhjoOpenId $ahjoOpenId,
    #[Autowire(service: 'logger.channel.paatokset_ahjo_api')]
    private readonly LoggerInterface $drupalLogger,
  ) {
    parent::__construct();
  }

  /**
   * Refresh AHJO auth token.
   */
  #[Command(name: 'ahjo-api:refresh-token')]
  public function refreshAuthToken(): int {
    try {
      $this->ahjoOpenId->refreshAuthToken();

      return DrushCommands::EXIT_SUCCESS;
    }
    catch (AhjoOpenIdException $e) {
      // logException does not work with Drush logger.
      Error::logException($this->drupalLogger, $e);
    }

    return DrushCommands::EXIT_FAILURE;
  }

}
