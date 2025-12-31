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
    'paatokset_ahjo_api',
    'helfi_api_base',
    'path_alias',
    'pathauto',
    'token',
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
   *
   * @covers ::create
   * @covers ::build
   */
  public function testBuild(): void {
    $block = PolicymakerSearchBlock::create($this->container, [], '', ['provider' => 'paatokset_search']);
    $build = $block->build();

    $this->assertSame('policymakers', $build['#search']['#attributes']['data-type']);
    $this->assertSame('Policymakers search description', $build['#lead_in']['#text']);
    $this->assertSame('processed_text', $build['#lead_in']['#type']);
    $this->assertSame('plain_text', $build['#lead_in']['#format']);
  }

}
