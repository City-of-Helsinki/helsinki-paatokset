<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset\Kernel\Lupapiste;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\paatokset\Plugin\Block\ArticlesBlock;
use Drupal\Tests\node\Traits\NodeCreationTrait;

/**
 * Tests articles block.
 *
 * @coversDefaultClass \Drupal\paatokset\Plugin\Block\ArticlesBlock
 */
class ArticlesBlockTest extends KernelTestBase {

  use NodeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'publication_date',
    'helfi_api_base',
    'serialization',
    'paatokset',
    'system',
    'node',
    'user',
  ];

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');

    NodeType::create([
      'name' => $this->randomMachineName(),
      'type' => 'article',
    ])->save();
  }

  /**
   * Tests article bundle class.
   */
  public function testBlock(): void {
    $this->createNode([
      'status' => NodeInterface::PUBLISHED,
      'published_at' => 1755855000,
      'type' => 'article',
    ]);

    $this->createNode([
      'status' => NodeInterface::PUBLISHED,
      'published_at' => 1724309500,
      'type' => 'article',
    ]);

    $block = ArticlesBlock::create($this->container, [], '', ['provider' => 'paatokset']);
    $build = $block->build();

    $this->assertArrayHasKey('2025', $build['#articles_by_year'] ?? []);
    $this->assertArrayHasKey('2024', $build['#articles_by_year'] ?? []);
  }

}
