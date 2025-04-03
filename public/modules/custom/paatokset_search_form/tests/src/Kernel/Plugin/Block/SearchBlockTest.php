<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_search_form\Kernel\Plugin\Block;

use Drupal\KernelTests\KernelTestBase;
use Drupal\paatokset_search_form\Plugin\Block\SearchBlock;

/**
 * Tests search block.
 *
 * @coversDefaultClass \Drupal\paatokset_search_form\Plugin\Block\SearchBlock
 */
class SearchBlockTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'paatokset_search',
    'paatokset_search_form',
  ];

  /**
   * Tests block render.
   *
   * @covers ::__construct
   * @covers ::create
   * @covers ::build
   */
  public function testBuild(): void {
    $block = SearchBlock::create($this->container, [], '', ['provider' => 'paatokset_search_form']);
    $build = $block->build();

    $this->assertContains('paatokset-search-wrapper', $build['search_wrapper']['#attributes']['class']);
    $this->assertContains('paatokset-search--frontpage', $build['#attributes']['class']);
    $this->assertArrayHasKey('search', $build['search_wrapper']);
  }

}
