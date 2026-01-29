<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\PolicymakerBrowserHeroBlockTest;

use Drupal\KernelTests\KernelTestBase;
use Drupal\paatokset_ahjo_api\Plugin\Block\PolicymakerBrowserHeroBlock;
use Symfony\Component\DependencyInjection\Loader\Configurator\Traits\PropertyTrait;

/**
 * Kernel tests for PolicymakerBrowserHeroBlock.
 *
 * @group paatokset_ahjo_api
 */
class PolicymakerBrowserHeroBlockTest extends KernelTestBase {

  use PropertyTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'block',
    'big_pipe',
    'paatokset_ahjo_api',
    'migrate',
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
    $this->installEntitySchema('user');
    $this->installConfig(['system']);
  }

  /**
   * Tests the build() method of the hero block.
   */
  public function testBuildMethod(): void {
    $plugin_definition = ['provider' => 'paatokset_ahjo_api'];

    $block = PolicymakerBrowserHeroBlock::create($this->container, [], 'policymaker_browser_hero_block', $plugin_definition);

    $build = $block->build();

    $this->assertIsArray($build);
    $this->assertArrayHasKey('policymaker_browser_hero_block', $build);

    $content = $build['policymaker_browser_hero_block'];
    $this->assertArrayHasKey('#theme', $content);
    $this->assertEquals('policymaker_browser_hero_block', $content['#theme']);
    $this->assertEquals('Browse policymakers', (string) $content['#hero_title']);
    $this->assertEquals('Browse the current policymakers.', (string) $content['#hero_description']);
  }

}
