<?php

declare(strict_types=1);

namespace Drupal\paatokset_datapumppu;

/**
 * A DTO to store command options.
 */
final readonly class DatapumppuImportOptions {

  /**
   * Start year of historical data in datapumppu.
   */
  public const DATAPUMPPU_FIRST_YEAR = 2017;

  /**
   * Constructs a new instance.
   *
   * @param 'all'|'latest' $dataset
   *   Trustee dataset. Can be 'latest' or 'all'.
   * @param int $startYear
   *   Statements import start year.
   * @param int $endYear
   *   Statement import end year.
   * @param bool $sync
   *   Delete existing statements before import.
   * @param ?string $trustee
   *   Import statements for specific trustee.
   */
  public function __construct(
    public string $dataset,
    public int $startYear,
    public int $endYear,
    public bool $sync = FALSE,
    public bool $update = FALSE,
    public ?string $trustee = NULL,
  ) {
    if ($this->startYear > $this->endYear || $this->startYear < self::DATAPUMPPU_FIRST_YEAR) {
      throw new \LogicException("Invalid start year");
    }
  }

  /**
   * Convert to array.
   *
   * @return array
   *   Array representation of self.
   */
  public function toArray(): array {
    return get_object_vars($this);
  }

  /**
   * Construct from options.
   *
   * @param array $options
   *   Drush command options.
   */
  public static function fromOptions(array $options): self {
    if (isset($options['year'], $options['start-year'])) {
      throw new \LogicException("year and start-year options are mutually exclusive");
    }

    $endYear = (int) date("Y");

    if (is_numeric($options['year'])) {
      $startYear = (int) $options['year'];
      $endYear = (int) $options['year'];
    }
    elseif (is_numeric($options['start-year'])) {
      $startYear = (int) $options['start-year'];
    }
    else {
      $startYear = $endYear;
    }

    $dataset = $options['dataset'] ?? 'all';
    $sync = (bool) $options['sync'];
    $update = (bool) $options['update'];
    $trustee = $options['trustee'] ?? NULL;

    return new self(
      $dataset,
      $startYear,
      $endYear,
      $sync,
      $update,
      $trustee,
    );
  }

}
