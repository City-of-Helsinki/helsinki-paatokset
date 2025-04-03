<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_search\Kernel\Plugin\Block;

use Drupal\KernelTests\KernelTestBase;
use Drupal\paatokset_search\Plugin\Block\PolicymakerSearchBlock;

/**
 * Tests policymaker search block.
 *
 * @coversDefaultClass \Drupal\paatokset_search\Plugin\Block\PolicymakerSearchBlock
 */
class PolicymakerSearchBlockTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'paatokset_search',
  ];

  /**
   * Tests block render.
   *
   * @covers ::__construct
   * @covers ::create
   * @covers ::build
   */
  public function testBuild(): void {
    $block = PolicymakerSearchBlock::create($this->container, [], '', ['provider' => 'paatokset_search']);
    $build = $block->build();

    $this->assertContains('paatokset-search-wrapper', $build['search_wrapper']['#attributes']['class']);
    $this->assertContains('policymaker-search', $build['#attributes']['class']);
    $this->assertArrayHasKey('search', $build['search_wrapper']);
  }

}
