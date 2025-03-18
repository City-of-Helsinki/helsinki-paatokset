<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_rss\Unit\Block;

use Drupal\Tests\helfi_platform_config\Unit\Block\BlockUnitTestBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\helfi_platform_config\EntityVersionMatcher;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\paatokset_rss\Plugin\Block\RssFeedBlock;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @coversDefaultClass \Drupal\paatokset_rss\Plugin\Block\RssFeedBlock
 *
 * @group paatokset_rss
 */
class RssFeedBlockTest extends BlockUnitTestBase {

  /**
   * The tested block.
   *
   * @var \Drupal\paatokset_rss\Plugin\Block\RssFeedBlock|\PHPUnit\Framework\MockObject\MockObject
   */
  private RssFeedBlock|MockObject $rssFeedBlock;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->rssFeedBlock = $this->getMockBuilder(RssFeedBlock::class)
      ->setConstructorArgs([
        [],
        'rss_feed',
        ['provider' => 'paatokset_rss'],
        $this->entityTypeManager,
        $this->entityVersionMatcher,
        $this->moduleHandler,
      ])
      ->onlyMethods(['getCurrentEntityVersion'])
      ->getMock();

    $this->rssFeedBlock->setStringTranslation($this->stringTranslation);
  }

  /**
   * Tests that render array is correctly built with a valid entity.
   *
   * @covers ::build
   */
  public function testBuildReturnsCorrectRenderArray(): void {
    $entity = $this->createMock(ContentEntityInterface::class);
    $this->createMockedEntity($entity);

    $this->rssFeedBlock->expects($this->any())
      ->method('getCurrentEntityVersion')
      ->willReturn(['entity' => $entity, 'entity_version' => EntityVersionMatcher::ENTITY_VERSION_REVISION]);

      // Build the expected output
    $expected = [
      'rss_feed' => [
        '#theme' => 'aggregator_feed',
        '#aggregator_feed' => $entity,
        '#cache' => [
          'tags' => [
            'aggregator_feed_view',
            'aggregator_feed:3',  // Ensure cache tags match the ID 3
          ],
          'contexts' => [],
          'max-age' => -1
        ],
        '#view_mode' => 'default',
        '#weight' => 0,
        '#pre_render' => [
          [ // This array is pre-rendered for the view builder
            0 => 'Drupal\aggregator\FeedViewBuilder',  // The view builder class
            1 => 'build'  // The build method of the view builder
          ]
        ],
      ],
    ];
      

    $this->assertEquals($expected, $this->rssFeedBlock->build());
  }

  /**
   * Create entity.
   *
   * @param \PHPUnit\Framework\MockObject\MockObject $entity
   *   Mocked entity.
   */
  private function createMockedEntity(MockObject &$entity): void {
    $entity->expects($this->any())
      ->method('getCacheTags')
      ->willReturn(['entity:aggregator_feed:1']);

    $entity->expects($this->any())
      ->method('id')
      ->willReturn(1);
  }

}
