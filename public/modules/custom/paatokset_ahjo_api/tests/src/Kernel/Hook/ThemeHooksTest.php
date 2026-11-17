<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\Hook;

use Drupal\block\Entity\Block;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\node\NodeInterface;
use Drupal\paatokset_ahjo_api\Entity\CaseBundle;
use Drupal\paatokset_ahjo_api\Hook\ThemeHooks;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\paatokset_ahjo_api\Kernel\AhjoEntityKernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests case and decision preprocess hooks.
 */
#[CoversClass(ThemeHooks::class)]
#[Group('paatokset_ahjo_api')]
#[RunTestsInSeparateProcesses]
class ThemeHooksTest extends AhjoEntityKernelTestBase {

  use NodeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
  ];

  /**
   * Tests case node preprocess.
   */
  public function testCasePreprocess(): void {
    $case = $this->createCaseWithDecisions('HEL-123');

    $build = $this->renderNode($case);

    $this->assertContains(CaseBundle::getCaseCacheTag('HEL-123'), $build['#cache']['tags']);
    $this->assertContains('languages:language_interface', $build['#cache']['contexts']);
  }

  /**
   * Tests case node preprocess without decisions.
   */
  public function testCasePreprocessWithoutDecisions(): void {
    $case = $this->createNode([
      'type' => 'case',
      'title' => 'NO TITLE',
      'field_diary_number' => 'HEL-456',
    ]);

    $build = $this->renderNode($case);

    $this->assertContains(CaseBundle::getCaseCacheTag('HEL-456'), $build['#cache']['tags']);
  }

  /**
   * Tests decision node preprocess.
   */
  public function testDecisionPreprocess(): void {
    $case = $this->createCaseWithDecisions('HEL-789');
    $decision = $this->createNode([
      'type' => 'decision',
      'title' => 'Middle decision',
      'field_diary_number' => 'HEL-789',
      'field_meeting_date' => '2019-01-01',
    ]);

    $build = $this->renderNode($decision);

    $this->assertContains(CaseBundle::getCaseCacheTag('HEL-789'), $build['#cache']['tags']);
    $this->assertContains('node:' . $case->id(), $build['#cache']['tags']);
    $this->assertContains('languages:language_interface', $build['#cache']['contexts']);
  }

  /**
   * Tests ahjo_case entity preprocess.
   */
  public function testAhjoCasePreprocess(): void {
    $case = $this->container->get(EntityTypeManagerInterface::class)
      ->getStorage('ahjo_case')
      ->create([
        'id' => 'HEL-321',
        'status' => '1',
        'langcode' => 'en',
      ]);
    $case->save();

    $build = $this->container->get(EntityTypeManagerInterface::class)
      ->getViewBuilder('ahjo_case')
      ->view($case, 'full');

    $this->assertNotFalse($this->container->get(RendererInterface::class)->renderInIsolation($build));
  }

  /**
   * Tests decision tree block preprocess.
   */
  public function testDecisionTreePreprocess(): void {
    $this->config('paatokset_ahjo_api.default_texts')
      ->set('committees_boards_url', '/committees')
      ->set('office_holders_url', '')
      ->save();

    $block = Block::create([
      'id' => 'decision_tree',
      'theme' => 'stark',
      'plugin' => 'decision_tree',
    ]);
    $block->save();

    $build = $this->container->get(EntityTypeManagerInterface::class)
      ->getViewBuilder('block')
      ->view($block);

    $output = (string) $this->container->get(RendererInterface::class)->renderInIsolation($build);

    $this->assertStringContainsString('Standard decision-making process', $output);
    $this->assertStringContainsString('href="/committees"', $output);
    // Falls back to the default URL when config is not set.
    $this->assertStringContainsString('href="/fi/paattajat"', $output);
  }

  /**
   * Creates a case with decisions.
   */
  private function createCaseWithDecisions(string $diaryNumber): NodeInterface {
    $case = $this->createNode([
      'type' => 'case',
      'title' => 'Test case',
      'field_full_title' => 'Test case full title',
      'field_diary_number' => $diaryNumber,
    ]);

    foreach (['2018-01-01', '2020-01-01'] as $date) {
      $this->createNode([
        'type' => 'decision',
        'title' => "Decision $date",
        'field_diary_number' => $diaryNumber,
        'field_meeting_date' => $date,
      ]);
    }

    return $case;
  }

  /**
   * Renders a node in full view mode.
   *
   * @return array<string, mixed>
   *   The render array with bubbled cacheability metadata.
   */
  private function renderNode(NodeInterface $node): array {
    $build = $this->container->get(EntityTypeManagerInterface::class)
      ->getViewBuilder('node')
      ->view($node, 'full');

    $this->container->get(RendererInterface::class)->renderInIsolation($build);

    return $build;
  }

}
