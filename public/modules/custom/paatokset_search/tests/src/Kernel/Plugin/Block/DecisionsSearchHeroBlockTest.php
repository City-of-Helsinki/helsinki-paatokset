<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_search\Kernel\Plugin\Block;

use Drupal\KernelTests\KernelTestBase;
use Drupal\paatokset_search\Plugin\Block\DecisionsSearchHeroBlock;

/**
 * Tests decisions search hero block.
 *
 * @coversDefaultClass \Drupal\paatokset_search\Plugin\Block\DecisionsSearchHeroBlock
 */
class DecisionsSearchHeroBlockTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'paatokset_search',
    'paatokset_ahjo_api',
    'helfi_api_base',
    'path_alias',
    'pathauto',
    'token',
    'migrate',
  ];

  /**
   * Tests block render.
   *
   * @covers ::build
   */
  public function testBuild(): void {
    $block = new DecisionsSearchHeroBlock([], '', ['provider' => 'paatokset_search']);
    $build = $block->build();

    $this->assertArrayHasKey('#hero_title', $build);
    $this->assertArrayHasKey('#hero_description', $build);
  }

}
