<?php

declare(strict_types=1);

namespace Drupal\paatokset_datapumppu\Service;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Drupal\paatokset_datapumppu\Entity\Statement;

/**
 * Statement service.
 */
final class StatementService {

  /**
   * Node storage service.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private EntityStorageInterface $statementStorage;

  /**
   * Date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  private DateFormatterInterface $dateFormatter;

  /**
   * Constructs StatementService.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, DateFormatterInterface $date_formatter) {
    $this->statementStorage = $entity_type_manager->getStorage('paatokset_statement');
    $this->dateFormatter = $date_formatter;
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
    $statements = $this->statementStorage
      ->getQuery()
      ->condition('speaker', $trustee->id())
      ->sort('start_time', 'DESC')
      ->execute();

    return $this->statementStorage->loadMultiple($statements);
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
