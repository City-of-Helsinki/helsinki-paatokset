<?php

declare(strict_types=1);

namespace App\Command;

use App\AhjoOpenId\AhjoOpenId;
use App\AhjoOpenId\AhjoOpenIdException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Refreshes the Ahjo auth token using the stored refresh token.
 */
#[AsCommand(name: 'ahjo-proxy:refresh-token', description: 'Refresh Ahjo auth token')]
final readonly class RefreshTokenCommand {

  public function __construct(
    private AhjoOpenId $ahjoOpenId,
  ) {
  }

  /**
   * Executes the command.
   */
  public function __invoke(SymfonyStyle $io): int {
    try {
      $token = $this->ahjoOpenId->refreshAuthToken();
    }
    catch (AhjoOpenIdException $e) {
      $io->error(sprintf('Failed to refresh token: %s', $e->getMessage()));
      return Command::FAILURE;
    }

    $io->success(sprintf('Token refreshed, valid until %s.', date(DATE_RFC2822, $token->expires)));
    return Command::SUCCESS;
  }

}
