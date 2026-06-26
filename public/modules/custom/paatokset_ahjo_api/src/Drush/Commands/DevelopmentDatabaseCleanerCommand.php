<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Drush\Commands;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drush\Commands\AutowireTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A drush command to clean up database for development purpose.
 */
#[AsCommand(
  name: self::NAME,
  description: 'Deletes old decision nodes from the database.',
)]
final class DevelopmentDatabaseCleanerCommand extends Command {

  use AutowireTrait;

  public const NAME = 'paatokset:decisions:delete';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EnvironmentResolverInterface $environmentResolver,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  protected function configure(): void {
    $this->addArgument(
      'date-from',
      InputArgument::OPTIONAL,
      'Decisions older than given date will be removed from database.',
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    try {
      $appEnv = $this->environmentResolver->getActiveEnvironmentName();
    }
    catch (\InvalidArgumentException) {
      $output->writeln('Stopping execution. No active environment found.');
      return self::SUCCESS;
    }

    // Only allowed in local and test environments.
    if (!in_array($appEnv, [EnvironmentEnum::Local->value, EnvironmentEnum::Test->value], TRUE)) {
      $output->writeln('Stopping execution. App environment is not "local" or "test".');
      return self::SUCCESS;
    }

    /** @var ?string $dateFrom */
    $dateFrom = $input->getArgument('date-from');

    $nodeStorage = $this->entityTypeManager->getStorage('node');
    $query = $nodeStorage->getQuery()->accessCheck(FALSE);

    $date = $dateFrom ? new DrupalDateTime($dateFrom) : new DrupalDateTime(date("Y-m-d", strtotime("-6 months")));

    $date->setTimezone(new \DateTimezone(DateTimeItemInterface::STORAGE_TIMEZONE));
    $formatted = $date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);

    $query
      ->condition('type', 'decision')
      ->condition('field_decision_date', $formatted, '<')
      ->range(0, 100);

    while ($ids = $query->execute()) {
      foreach ($ids as $id) {
        $node = $nodeStorage->load($id);
        $node->delete();
      }
    }

    return self::SUCCESS;
  }

}
