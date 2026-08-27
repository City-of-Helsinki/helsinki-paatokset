<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Drush\Commands;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Queue\QueueFactory;
use Drush\Commands\AutowireTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Re-imports decisions that still have legacy format content.
 *
 * Ahjo changed the decision HTML format in 2025, which forces
 * DecisionParser to support both formats. The stored HTML is whatever
 * Ahjo returned at import time, so the only way to convert a decision
 * is to fetch it again.
 *
 * This command finds decisions that still have the legacy format and
 * adds them to the aggregation queue, which re-imports them one by one.
 * Once no legacy decisions are left, both the legacy parsing and this
 * command can be removed.
 *
 * @see \Drupal\paatokset_ahjo_api\Decisions\DecisionParser::getMoreInfoDetails()
 * @see \Drupal\paatokset_ahjo_api\Queue\AhjoQueueWorkerBase::processLegacyItem()
 */
#[AsCommand(
  name: self::NAME,
  description: 'Re-imports decisions that still have legacy format content.',
)]
final class ReimportLegacyDecisionsCommand extends Command {

  use AutowireTrait;

  public const NAME = 'ahjo-api:decisions:reimport-legacy';

  /**
   * Heading that is present in both the legacy and the new format.
   */
  private const MORE_INFO_HEADING = 'LisatiedotOtsikko';

  /**
   * Span that only the new format has.
   */
  private const MORE_INFO_NAME = 'LisatiedonantajanNimi';

  /**
   * Queue that re-imports single Ahjo entities.
   */
  private const QUEUE = 'ahjo_api_aggregation_queue';

  public function __construct(
    private readonly Connection $database,
    private readonly QueueFactory $queueFactory,
    #[Autowire(service: 'logger.channel.paatokset_ahjo_api')]
    private readonly LoggerInterface $drupalLogger,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritDoc}
   */
  protected function configure(): void {
    $this
      ->addOption(
        'limit',
        NULL,
        InputOption::VALUE_REQUIRED,
        'Maximum number of decisions to add to the queue. Defaults to 1000.',
        1000,
      )
      ->addUsage(self::NAME . ' --limit=10');
  }

  /**
   * {@inheritDoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $total = (int) $this->getQuery()
      ->countQuery()
      ->execute()
      ->fetchField();

    $output->writeln(sprintf('Found %d decisions with legacy format content.', $total));

    if ($total === 0) {
      return self::SUCCESS;
    }

    $query = $this->getQuery();
    $limit = (int) $input->getOption('limit');
    if ($limit > 0) {
      $query->range(0, $limit);
    }

    $queue = $this->queueFactory->get(self::QUEUE);

    $count = 0;
    foreach ($query->execute() as $row) {
      // The queue worker passes items to AhjoProxy::migrateSingleEntity().
      $queue->createItem([
        'id' => 'decisions',
        'content' => (object) [
          'updatetype' => 'AddedFromDrush',
          'id' => $row->native_id,
        ],
        'created' => (int) (new \DateTimeImmutable())->format('U'),
        'request' => [],
      ]);

      $count++;
    }

    $this->drupalLogger->info('Added @count legacy decisions to @queue.', [
      '@count' => $count,
      '@queue' => self::QUEUE,
    ]);

    $output->writeln(sprintf('Added %d decisions to %s.', $count, self::QUEUE));
    $output->writeln(sprintf('Run `drush queue:run %s` to re-import them.', self::QUEUE));

    return self::SUCCESS;
  }

  /**
   * Builds a query for decisions that still have legacy format content.
   *
   * @return \Drupal\Core\Database\Query\SelectInterface
   *   Query returning the node ID and the Ahjo native ID.
   */
  private function getQuery(): SelectInterface {
    $query = $this->database->select('node__field_decision_content', 'c');
    $query->innerJoin('node__field_decision_native_id', 'n', 'n.entity_id = c.entity_id');

    // The node ID is selected so the query can be ordered by it.
    $query->addField('c', 'entity_id', 'nid');
    $query->addField('n', 'field_decision_native_id_value', 'native_id');
    $query->condition('c.bundle', 'decision');
    $query->condition('c.field_decision_content_value', '%' . $this->database->escapeLike(self::MORE_INFO_HEADING) . '%', 'LIKE');
    $query->condition('c.field_decision_content_value', '%' . $this->database->escapeLike(self::MORE_INFO_NAME) . '%', 'NOT LIKE');
    $query->distinct();
    $query->orderBy('c.entity_id');

    return $query;
  }

}
