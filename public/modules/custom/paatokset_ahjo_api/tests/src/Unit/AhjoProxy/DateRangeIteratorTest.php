<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Unit\AhjoProxy;

use Drupal\paatokset_ahjo_api\AhjoProxy\DateRangeIterator;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests DateRangeIterator.
 */
#[CoversClass(DateRangeIterator::class)]
class DateRangeIteratorTest extends UnitTestCase {

  /**
   * Tests iteration over a date range that divides evenly.
   */
  public function testEvenlyDivisibleRange(): void {
    $start = new \DateTimeImmutable('2024-01-01');
    $end = new \DateTimeImmutable('2024-01-31');
    $interval = new \DateInterval('P10D');

    $iterator = new DateRangeIterator($start, $end, $interval);
    $chunks = iterator_to_array($iterator);

    $this->assertCount(3, $chunks);

    // First chunk: Jan 1 - Jan 11.
    $this->assertEquals('2024-01-01', $chunks[0][0]->format('Y-m-d'));
    $this->assertEquals('2024-01-11', $chunks[0][1]->format('Y-m-d'));

    // Second chunk: Jan 11 - Jan 21.
    $this->assertEquals('2024-01-11', $chunks[1][0]->format('Y-m-d'));
    $this->assertEquals('2024-01-21', $chunks[1][1]->format('Y-m-d'));

    // Third chunk: Jan 21 - Jan 31 (capped at end).
    $this->assertEquals('2024-01-21', $chunks[2][0]->format('Y-m-d'));
    $this->assertEquals('2024-01-31', $chunks[2][1]->format('Y-m-d'));
  }

  /**
   * Tests that the last chunk is capped at the end date.
   */
  public function testLastChunkCappedAtEnd(): void {
    $start = new \DateTimeImmutable('2024-01-01');
    $end = new \DateTimeImmutable('2024-01-15');
    $interval = new \DateInterval('P1M');

    $iterator = new DateRangeIterator($start, $end, $interval);
    $chunks = iterator_to_array($iterator);

    $this->assertCount(1, $chunks);
    $this->assertEquals('2024-01-01', $chunks[0][0]->format('Y-m-d'));
    $this->assertEquals('2024-01-15', $chunks[0][1]->format('Y-m-d'));
  }

  /**
   * Tests empty range when start equals end.
   */
  public function testEmptyRangeWhenStartEqualsEnd(): void {
    $date = new \DateTimeImmutable('2024-01-01');
    $interval = new \DateInterval('P1D');

    $iterator = new DateRangeIterator($date, $date, $interval);
    $chunks = iterator_to_array($iterator);

    $this->assertCount(0, $chunks);
  }

  /**
   * Tests empty range when start is after end.
   */
  public function testEmptyRangeWhenStartAfterEnd(): void {
    $start = new \DateTimeImmutable('2024-01-15');
    $end = new \DateTimeImmutable('2024-01-01');
    $interval = new \DateInterval('P1D');

    $iterator = new DateRangeIterator($start, $end, $interval);
    $chunks = iterator_to_array($iterator);

    $this->assertCount(0, $chunks);
  }

  /**
   * Tests single day range.
   */
  public function testSingleDayRange(): void {
    $start = new \DateTimeImmutable('2024-01-01');
    $end = new \DateTimeImmutable('2024-01-02');
    $interval = new \DateInterval('P1W');

    $iterator = new DateRangeIterator($start, $end, $interval);
    $chunks = iterator_to_array($iterator);

    $this->assertCount(1, $chunks);
    $this->assertEquals('2024-01-01', $chunks[0][0]->format('Y-m-d'));
    $this->assertEquals('2024-01-02', $chunks[0][1]->format('Y-m-d'));
  }

  /**
   * Tests count method.
   */
  public function testCount(): void {
    $start = new \DateTimeImmutable('2024-01-01');
    $end = new \DateTimeImmutable('2024-01-31');
    $interval = new \DateInterval('P1W');

    $iterator = new DateRangeIterator($start, $end, $interval);

    // 30 days / 7 days per week = ~4.3 chunks, so 5 chunks.
    $this->assertCount(5, $iterator);
  }

}
