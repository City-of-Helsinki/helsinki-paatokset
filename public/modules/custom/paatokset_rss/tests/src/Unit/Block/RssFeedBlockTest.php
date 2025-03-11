<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_rss\Unit\Block;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\paatokset_rss\Plugin\Block\RssFeedBlock;
use Drupal\Tests\UnitTestCase;
use Drupal\aggregator\Entity\Feed;

/**
 * @coversDefaultClass \Drupal\paatokset_rss\Plugin\Block\RssFeedBlock
 *
 * @group paatokset_rss
 */
class RssFeedBlockTest extends UnitTestCase {

  /**
   * The block instance being tested.
   *
   * @var \Drupal\paatokset_rss\Plugin\Block\RssFeedBlock
   */
  private RssFeedBlock $rssFeedBlock;

  /**
   * Mocked entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * Mocked aggregator feed storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private $feedStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Mock the entity storage for aggregator_feed.
    $this->feedStorage = $this->createMock(EntityStorageInterface::class);

    // Mock the entity type manager and return the storage when requested.
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->entityTypeManager->method('getStorage')
      ->with('aggregator_feed')
      ->willReturn($this->feedStorage);

    // Create the block instance with dependency injection.
    $this->rssFeedBlock = new RssFeedBlock(
      [],
      'rss_feed',
      ['provider' => 'paatokset_rss'],
      $this->entityTypeManager
    );

    // Set translation service for StringTranslationTrait.
    $this->rssFeedBlock->setStringTranslation($this->getStringTranslationStub());
  }

  /**
   * Tests that blockForm() returns the expected form structure.
   *
   * @covers ::blockForm
   */
  public function testBlockForm(): void {
    $form_state = $this->createMock(FormStateInterface::class);
    $form = [];

    $form = $this->rssFeedBlock->blockForm($form, $form_state);

    // Verify that the form contains the expected field.
    $this->assertArrayHasKey('aggregator_feed', $form);

    // Verify field type and description.
    $this->assertEquals('entity_autocomplete', $form['aggregator_feed']['#type']);
    $this->assertEquals('aggregator_feed', $form['aggregator_feed']['#target_type']);
    $this->assertEquals('default', $form['aggregator_feed']['#selection_handler']);
    $this->assertEquals('Select an RSS feed from the aggregator module.', (string) $form['aggregator_feed']['#description']);
  }

  /**
   * Tests that submit saves the form values and updates configuration.
   *
   * @covers ::blockSubmit
   */
  public function testBlockSubmit(): void {
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('getValue')->with('aggregator_feed')->willReturn('3');

    // Submit the form.
    $this->rssFeedBlock->blockSubmit([], $form_state);

    // Verify that the configuration is saved correctly.
    $this->assertSame('3', $this->rssFeedBlock->getConfiguration()['aggregator_feed']);
  }

  /**
   * Tests that build() renders the selected RSS feed.
   *
   * @covers ::build
   */
  public function testBuild(): void {
    // Create a mock feed entity.
    $feed = $this->createMock(Feed::class);
    $feed->method('label')->willReturn('Test RSS Feed');

    // Set up mock storage to return the feed.
    $this->feedStorage->method('load')->with('3')->willReturn($feed);

    // Mock the view builder
    $view_builder = $this->createMock(\Drupal\Core\Entity\EntityViewBuilder::class);
    $view_builder->method('view')->with($feed, 'default')->willReturn([
      '#markup' => 'RSS Feed Content'
    ]);

    // Mock getViewBuilder to return the view builder mock
    $this->entityTypeManager->method('getViewBuilder')
      ->with('aggregator_feed')
      ->willReturn($view_builder);

    // Set the block's configuration.
    $this->rssFeedBlock->setConfiguration(['aggregator_feed' => '3']);

    // Call build() method.
    $build = $this->rssFeedBlock->build();

    // Verify that build contains the expected render array.
    $this->assertArrayHasKey('rss_feed', $build);
    $this->assertEquals(['#markup' => 'RSS Feed Content'], $build['rss_feed']);
  }

}
