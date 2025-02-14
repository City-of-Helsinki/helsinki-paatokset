<?php

namespace Drupal\Tests\paatokset_allu\Unit;

use Drupal\paatokset_allu\DocumentType;
use Drupal\paatokset_allu\Plugin\Deriver\AlluSourcePlugin;
use Drupal\Tests\UnitTestCase;

/**
 * Tests plugin deriver.
 *
 * @group paatokset_allu
 */
class DeriverTest extends UnitTestCase {

  /**
   * Tests plugin deriver.
   */
  public function testDeriver(): void {
    $sut = new AlluSourcePlugin();
    $definitions = $sut->getDerivativeDefinitions(['foo' => 'bar']);

    foreach ($definitions as $key => $definition) {
      $this->assertNotEmpty(DocumentType::tryFrom($key));
      $this->assertArrayHasKey('document', $definition);
    }
  }

}
