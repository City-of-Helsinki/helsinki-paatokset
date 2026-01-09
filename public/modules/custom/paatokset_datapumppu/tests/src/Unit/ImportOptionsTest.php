<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_datapumppu\Kernel\Unit;

use Drupal\paatokset_datapumppu\DatapumppuImportOptions;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Test datapumppu import options.
 */
class ImportOptionsTest extends UnitTestCase {

  /**
   * Test mutually exclusive arguments.
   */
  public function testFromOptions(): void {
    $options = DatapumppuImportOptions::fromOptions(['year' => '2020']);
    $this->assertEquals(2020, $options->startYear);
    $this->assertEquals(2020, $options->endYear);

    $options = DatapumppuImportOptions::fromOptions([]);
    $currentYear = (int) date('Y');
    $this->assertEquals($currentYear, $options->startYear);
    $this->assertEquals($currentYear, $options->endYear);

    $options = DatapumppuImportOptions::fromOptions(['start-year' => 2020]);
    $this->assertEquals(2020, $options->startYear);
    $this->assertEquals($currentYear, $options->endYear);
  }

  /**
   * Test exceptions.
   */
  #[DataProvider('dataProvider')]
  public function testExceptions(array $options): void {
    $this->expectException(\LogicException::class);
    DatapumppuImportOptions::fromOptions($options);
  }

  /**
   * Data provider for exception tests.
   */
  public static function dataProvider(): array {
    return [
      // Mutually exclusive arguments.
      [
        [
          'start-year' => 2025,
          'year' => '2025',
        ],
      ],
      // Invalid start year.
      [
        [
          'start-year' => (int) date("Y") + 200,
        ],
      ],
    ];
  }

}
