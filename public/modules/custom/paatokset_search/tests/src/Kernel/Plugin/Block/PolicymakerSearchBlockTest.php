<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_search\Kernel\Plugin\Block;

use Drupal\paatokset_search\Plugin\Block\PolicymakerSearchBlock;
use Drupal\Tests\paatokset_ahjo_api\Kernel\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests policymaker search block.
 */
#[RunTestsInSeparateProcesses]
#[Group('paatokset_search')]
class PolicymakerSearchBlockTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'paatokset_search',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Set up the default texts.
    $config = $this->config('paatokset_ahjo_api.default_texts');
    $config->set('policymakers_search_description', [
      'value' => 'Policymakers search description',
      'format' => 'plain_text',
    ]);
    $config->save();
  }

  /**
   * Tests block render.
   */
  public function testBuild(): void {
    $block = PolicymakerSearchBlock::create($this->container, [], '', ['provider' => 'paatokset_search']);
    $build = $block->build();

    $this->assertSame('policymakers', $build['#search_element']['#attributes']['data-type']);
    $this->assertSame('Policymakers search description', $build['#lead_in']['#text']);
    $this->assertSame('processed_text', $build['#lead_in']['#type']);
    $this->assertSame('plain_text', $build['#lead_in']['#format']);
  }

}
