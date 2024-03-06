<?php

declare(strict_types=1);

namespace Drupal\paatokset_search\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Attributes\Command;
use Drush\Commands\DrushCommands;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\node\Entity\Node;


/**
 * A drush command to clean up database for development purpose.
 */
final class DevelopmentDatabaseCleanerCommand extends DrushCommands {

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  )
  {
  }

  /**
   * Deletes the old decisions.
   *
   * @param array $options
   *   The options.
   *
   * @return int
   *   The exit code.
   */
  #[Command(name: 'paatokset:decisions:delete')]
  public function databaseCleanup(string $dateFrom = null): int {

    if (getenv('APP_ENV') !== 'local') {
      $this->io()->writeln('Stopping execution. APP_ENV is not "local" or is missing.');
      return DrushCommands::EXIT_SUCCESS;
    }

    $query = $this->entityTypeManager
      ->getStorage('node')
      ->getQuery();

    $date = $dateFrom ? new DrupalDateTime($dateFrom) : new DrupalDateTime(date("Y-m-d", strtotime("-6 months")));

    $date->setTimezone(new \DateTimezone(DateTimeItemInterface::STORAGE_TIMEZONE));
    $formatted = $date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);

    $query
      ->condition('type', 'decision')
      ->condition('field_decision_date', $formatted, '<')
      ->accessCheck(FALSE)
      ->range(0,100);

    while($ids = $query->execute()) {
      foreach ($ids as $id) {
        $node = Node::load($id);
        $node->delete();
      }
    }

    return DrushCommands::EXIT_SUCCESS;
  }

}
