<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Drush\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\paatokset_ahjo_api\Routing\LanguageAliasGenerator;
use Drush\Commands\AutowireTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Generates path aliases for decisions and cases in UI languages.
 *
 * New and updated nodes get the aliases automatically.
 * This command backfills aliases for existing content.
 *
 * @see \Drupal\paatokset_ahjo_api\Routing\LanguageAliasGenerator
 */
#[AsCommand(
  name: self::NAME,
  description: 'Generates decision and case path aliases for UI languages.',
)]
final class GenerateLanguageAliasesCommand extends Command {

  use AutowireTrait;

  public const string NAME = 'paatokset:language-aliases';

  private const array BUNDLES = ['decision', 'case'];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LanguageAliasGenerator $generator,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  protected function configure(): void {
    $this->addOption(
      name: 'bundle',
      mode: InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
      description: 'Only process given bundle.',
      default: self::BUNDLES,
      suggestedValues: self::BUNDLES,
    );
    $this->addOption(
      name: 'batch-size',
      mode: InputOption::VALUE_REQUIRED,
      description: 'Number of nodes loaded at once.',
      default: 200,
    );
    $this->addOption(
      name: 'from-nid',
      mode: InputOption::VALUE_REQUIRED,
      description: 'Start from given node id.',
      default: 0,
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $bundles = (array) $input->getOption('bundle');
    $batchSize = max(1, (int) $input->getOption('batch-size'));
    $fromNid = (int) $input->getOption('from-nid');

    if ($invalid = array_diff($bundles, self::BUNDLES)) {
      $output->writeln(sprintf('<error>Invalid bundle: %s</error>', implode(', ', $invalid)));
      return self::INVALID;
    }

    $storage = $this->entityTypeManager->getStorage('node');

    $total = (int) $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', $bundles, 'IN')
      ->condition('nid', $fromNid, '>')
      ->count()
      ->execute();

    $progress = new ProgressBar($output, $total);
    $progress->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% nid: %message%');
    $progress->setMessage((string) $fromNid);
    $progress->start();

    $lastNid = $fromNid;
    $changed = 0;

    while (TRUE) {
      $ids = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', $bundles, 'IN')
        ->condition('nid', $lastNid, '>')
        ->sort('nid')
        ->range(0, $batchSize)
        ->execute();

      if (empty($ids)) {
        break;
      }

      foreach ($storage->loadMultiple($ids) as $node) {
        assert($node instanceof NodeInterface);
        $changed += $this->generator->generate($node);
      }

      $lastNid = (int) end($ids);
      $storage->resetCache($ids);

      $progress->setMessage((string) $lastNid);
      $progress->advance(count($ids));
    }

    $progress->finish();
    $output->writeln('');
    $output->writeln(sprintf('Created or updated %d aliases.', $changed));

    return self::SUCCESS;
  }

}
