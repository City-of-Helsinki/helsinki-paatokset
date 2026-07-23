<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_search\Kernel\Plugin\Block;

use Drupal\KernelTests\KernelTestBase;
use Drupal\paatokset_search\Plugin\Block\PolicymakerSearchHeroBlock;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests policymaker search hero block.
 */
#[RunTestsInSeparateProcesses]
#[Group('paatokset_search')]
class PolicymakerSearchHeroBlockTest extends KernelTestBase {

  /**
   * Tests block render.
   */
  public function testBuild(): void {
    $block = new PolicymakerSearchHeroBlock([], '', ['provider' => 'paatokset_search']);
    $build = $block->build();

    $this->assertArrayHasKey('#hero_title', $build);
    $this->assertArrayHasKey('#hero_description', $build);
  }

}
