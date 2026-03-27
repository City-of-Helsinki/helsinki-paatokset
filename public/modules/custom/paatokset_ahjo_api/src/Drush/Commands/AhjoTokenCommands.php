<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Drush\Commands;

use Drupal\Core\Utility\Error;
use Drupal\paatokset_ahjo_api\AhjoOpenId\AhjoOpenId;
use Drupal\paatokset_ahjo_api\AhjoOpenId\AhjoOpenIdException;
use Drush\Commands\AutowireTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Ahjo token commands.
 */
#[AsCommand(
  name: 'ahjo-api:refresh-token',
  description: 'Refresh AHJO auth token.',
)]
final class AhjoTokenCommands extends Command {

  use AutowireTrait;

  public function __construct(
    private readonly AhjoOpenId $ahjoOpenId,
    #[Autowire(service: 'logger.channel.paatokset_ahjo_api')]
    private readonly LoggerInterface $drupalLogger,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritDoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    try {
      $this->ahjoOpenId->refreshAuthToken();

      return self::SUCCESS;
    }
    catch (AhjoOpenIdException $e) {
      // logException does not work with Drush logger.
      Error::logException($this->drupalLogger, $e);
    }

    return self::FAILURE;
  }

}
