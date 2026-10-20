<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset\Kernel\Plugin\Block;

use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\paatokset\Plugin\Block\DictionaryBannerBlock;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the dictionary banner block.
 */
#[RunTestsInSeparateProcesses]
#[Group('paatokset')]
class DictionaryBannerBlockTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'diff',
    'helfi_api_base',
    'node',
    'serialization',
    'system',
    'user',
    'paatokset',
  ];

  /**
   * Tests block build.
   */
  public function testBlock(): void {
    $block = $this->container
      ->get(BlockManagerInterface::class)
      ->createInstance('dictionary_banner');
    $this->assertInstanceOf(DictionaryBannerBlock::class, $block);
    $this->assertEquals([], $block->build());

    $block->setConfigurationValue('dictionary_node_id', 690);
    $build = $block->build();
    $this->assertEquals('/node/690', $build['#dictionary_url']->toString());

    $markup = (string) $this->container->get(RendererInterface::class)->renderInIsolation($build);
    $this->assertStringContainsString('href="/node/690"', $markup);
  }

}
