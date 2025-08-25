<?php

namespace Drupal\Tests\paatokset\Kernel\Entity;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\paatokset\Entity\Article;
use Drupal\Tests\node\Traits\NodeCreationTrait;

/**
 * Tests article bundle class.
 *
 * @coversDefaultClass \Drupal\paatokset\Entity\Article
 */
class ArticleTest extends KernelTestBase {

  use NodeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'publication_date',
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
  public function testArticle(): void {
    $node = $this->createNode([
      'status' => NodeInterface::PUBLISHED,
      'published_at' => 1755855000,
      'type' => 'article',
    ]);

    $this->assertInstanceOf(Article::class, $node);
    $this->assertEquals('2025', $node->getPublishedYear());
  }

}
