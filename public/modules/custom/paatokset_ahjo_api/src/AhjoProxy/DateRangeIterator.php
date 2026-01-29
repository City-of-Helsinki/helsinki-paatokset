<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\AhjoProxy;

/**
 * Iterates over a date range in chunks.
 *
 * Splits a date range into smaller intervals for batched API requests.
 *
 * @implements \IteratorAggregate<int, array{0: \DateTimeImmutable, 1: \DateTimeImmutable}>
 */
final readonly class DateRangeIterator implements \IteratorAggregate, \Countable {

  public function __construct(
    private \DateTimeImmutable $start,
    private \DateTimeImmutable $end,
    private \DateInterval $interval,
  ) {
  }

  /**
   * {@inheritdoc}
   *
   * @return \Generator<int, array{0: \DateTimeImmutable, 1: \DateTimeImmutable}>
   *   Yields arrays containing [chunkStart, chunkEnd] date pairs.
   */
  public function getIterator(): \Generator {
    for ($current = $this->start; $current < $this->end; $current = $next) {
      $next = $current->add($this->interval);
      if ($next > $this->end) {
        $next = $this->end;
      }

      yield [$current, $next];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function count(): int {
    return iterator_count($this->getIterator());
  }

}
