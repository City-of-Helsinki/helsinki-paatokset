<?php

declare(strict_types=1);

namespace Drupal\paatokset_datapumppu\Service;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Drupal\paatokset_datapumppu\Entity\Statement;

/**
 * Statement service.
 */
readonly class StatementService {

  /**
   * Constructs StatementService.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date formatter.
   */
  public function __construct(
    private EntityTypeManagerInterface $entityTypeManager,
    private DateFormatterInterface $dateFormatter,
  ) {
  }

  /**
   * Get statements made by trustee.
   *
   * @param \Drupal\node\NodeInterface $trustee
   *   The trustee node.
   *
   * @return \Drupal\paatokset_datapumppu\Entity\Statement[]
   *   Found statement entities.
   */
  public function getStatementsByTrustee(NodeInterface $trustee): array {
    $statementStorage = $this->entityTypeManager
      ->getStorage('paatokset_statement');

    $statements = $statementStorage
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('speaker', $trustee->id())
      ->sort('start_time', 'DESC')
      ->execute();

    /** @var \Drupal\paatokset_datapumppu\Entity\Statement[] $entities */
    $entities = $statementStorage->loadMultiple($statements);

    return $entities;
  }

  /**
   * Get formatted title of the string.
   *
   * @param \Drupal\paatokset_datapumppu\Entity\Statement $statement
   *   The statement entity.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   Formatted title.
   */
  public function formatStatementTitle(Statement $statement): TranslatableMarkup {
    // @phpstan-ignore-next-line.
    $startTime = $statement->get('start_time')->date;
    $startDate = $this->dateFormatter->format($startTime->getTimestamp(), 'custom', 'd.m.Y');

    $duration = $statement->get('duration')->value;
    $duration = $this->dateFormatter->formatInterval($duration);

    $case = $statement->get('case_number')->getString();
    $title = $statement->get('title')->getString();

    return new TranslatableMarkup("City Council @date, case @case: @title - Floor (@duration)", [
      '@date' => $startDate,
      '@case' => $case,
      '@title' => $title,
      '@duration' => $duration,
    ]);
  }

}
