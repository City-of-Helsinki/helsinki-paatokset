<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\Routing;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\helfi_api_base\Traits\LanguageManagerTrait;
use Drupal\Tests\paatokset_ahjo_api\Kernel\AhjoEntityKernelTestBase;
use Drupal\Tests\paatokset_ahjo_api\Kernel\DecisionPathPatternsTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests localized case and decision paths.
 *
 * @see \Drupal\paatokset_ahjo_api\Routing\DecisionPathProcessor
 */
#[Group('paatokset_ahjo_api')]
#[RunTestsInSeparateProcesses]
class DecisionPathProcessorTest extends AhjoEntityKernelTestBase {

  use LanguageManagerTrait;
  use DecisionPathPatternsTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_language_negotiator_test',
    'language',
    'path',
    // Token module requires filter for text field tokens.
    'filter',
  ];

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->setupLanguages();
    $this->installConfig(['pathauto']);
    $this->createDecisionPathPatterns();
  }

  /**
   * Creates a node. Pathauto creates the alias in the node language.
   *
   * @param array<string, mixed> $values
   *   Node values.
   */
  private function createNode(array $values): NodeInterface {
    $node = $this->container->get(EntityTypeManagerInterface::class)
      ->getStorage('node')
      ->create($values + [
        'title' => 'Test',
        'status' => 1,
        'langcode' => 'fi',
      ]);
    $node->save();

    assert($node instanceof NodeInterface);
    return $node;
  }

  /**
   * Gets node path in given language.
   *
   * URL language prefixes are not configured in kernel tests.
   */
  private function getPath(NodeInterface $node, string $langcode): string {
    return $node->toUrl('canonical', [
      'language' => $this->languageManager()->getLanguage($langcode),
    ])->toString();
  }

  /**
   * Runs inbound path processing with given current language.
   */
  private function processInbound(string $path, string $langcode): string {
    $this->setOverrideLanguageCode($langcode);

    // Run the whole processor chain, so the core alias processor is included.
    return $this->container->get('path_processor_manager')
      ->processInbound($path, Request::create($path));
  }

  /**
   * Tests outbound and inbound paths.
   */
  public function testPaths(): void {
    $case = $this->createNode([
      'type' => 'case',
      'field_diary_number' => 'HEL-2022-008715',
    ]);

    $decision = $this->createNode([
      'type' => 'decision',
      'field_diary_number' => 'HEL-2022-008715',
      'field_decision_native_id' => '{1C7D448B}',
      'field_unique_id' => 'unique-1',
    ]);

    // Swedish version of the decision is a separate node.
    $swedishDecision = $this->createNode([
      'type' => 'decision',
      'langcode' => 'sv',
      'field_diary_number' => 'HEL-2022-008715',
      'field_decision_native_id' => '{223A28F0}',
      'field_unique_id' => 'unique-1',
    ]);

    $decisionWithoutCase = $this->createNode([
      'type' => 'decision',
      'field_decision_native_id' => '{2D3DD3D2}',
    ]);

    // Other content is not affected.
    $policymaker = $this->createNode([
      'type' => 'policymaker',
    ]);

    // Node => path without the language specific prefix.
    $tests = [
      [$case, 'hel-2022-008715'],
      [$decision, 'hel-2022-008715/1c7d448b'],
      [$swedishDecision, 'hel-2022-008715/223a28f0'],
      [$decisionWithoutCase, '2d3dd3d2'],
    ];

    foreach ($tests as [$node, $rest]) {
      [$fi, $sv, $en] = ["/asia/$rest", "/arende/$rest", "/case/$rest"];
      $source = '/node/' . $node->id();

      $this->assertEquals($fi, $this->getPath($node, 'fi'));
      $this->assertEquals($sv, $this->getPath($node, 'sv'));
      $this->assertEquals($en, $this->getPath($node, 'en'));

      $this->assertEquals($source, $this->processInbound($fi, 'fi'));
      $this->assertEquals($source, $this->processInbound($sv, 'sv'));
      $this->assertEquals($source, $this->processInbound($en, 'en'));

      // Prefix must match the current language.
      $this->assertEquals($sv, $this->processInbound($sv, 'fi'));
      $this->assertEquals($fi, $this->processInbound($fi, 'sv'));
    }

    $this->assertEquals('/node/' . $policymaker->id(), $this->getPath($policymaker, 'sv'));

    // Unknown paths are not changed.
    $this->assertEquals('/arende/does-not-exist', $this->processInbound('/arende/does-not-exist', 'sv'));
    $this->assertEquals('/arende/', $this->processInbound('/arende/', 'sv'));

    // Unaliased URLs are not changed.
    $this->assertEquals('/node/' . $decision->id(), $decision->toUrl('canonical', [
      'language' => $this->languageManager()->getLanguage('sv'),
      'alias' => TRUE,
    ])->toString());
  }

}
