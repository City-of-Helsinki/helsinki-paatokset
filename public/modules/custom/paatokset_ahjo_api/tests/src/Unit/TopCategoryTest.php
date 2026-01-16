<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokest_ahjo_api\Unit;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\paatokset_ahjo_api\Entity\TopCategory;
use Drupal\Tests\UnitTestCase;

/**
 * Tests top category enum.
 */
class TopCategoryTest extends UnitTestCase {

  /**
   * Tests that all top category cases have a label.
   */
  public function testTopCategoryLabels(): void {
    // All top category cases should have label defined.
    foreach (TopCategory::cases() as $case) {
      $this->assertInstanceOf(TranslatableMarkup::class, $case->getLabel());
    }
  }

}
