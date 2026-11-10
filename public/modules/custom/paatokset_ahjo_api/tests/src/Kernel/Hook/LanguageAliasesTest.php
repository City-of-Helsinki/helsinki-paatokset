<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\paatokset_ahjo_api\Drush\Commands\GenerateLanguageAliasesCommand;
use Drupal\paatokset_ahjo_api\Routing\LanguageAliasGenerator;
use Drupal\pathauto\PathautoState;
use Drupal\Tests\helfi_api_base\Traits\LanguageManagerTrait;
use Drupal\Tests\paatokset_ahjo_api\Kernel\AhjoEntityKernelTestBase;
use Drupal\Tests\paatokset_ahjo_api\Kernel\DecisionPathPatternsTrait;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests decision and case path aliases.
 *
 * @see \Drupal\paatokset_ahjo_api\Hook\LanguageAliases
 * @see \Drupal\paatokset_ahjo_api\Routing\LanguageAliasGenerator
 * @see \Drupal\paatokset_ahjo_api\Drush\Commands\GenerateLanguageAliasesCommand
 */
class LanguageAliasesTest extends AhjoEntityKernelTestBase {

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
   * Creates a node.
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
   * Gets aliases for given node keyed by langcode.
   *
   * @return array<string, string>
   *   Aliases keyed by langcode.
   */
  private function getAliases(NodeInterface $node): array {
    $aliases = $this->container->get(EntityTypeManagerInterface::class)
      ->getStorage('path_alias')
      ->loadByProperties(['path' => '/node/' . $node->id()]);

    $result = [];
    foreach ($aliases as $alias) {
      /** @var \Drupal\path_alias\PathAliasInterface $alias */
      $this->assertArrayNotHasKey($alias->language()->getId(), $result, 'Only one alias per language');
      $result[$alias->language()->getId()] = $alias->getAlias();
    }
    ksort($result);

    return $result;
  }

  /**
   * Tests that aliases are created for all languages.
   */
  public function testAliases(): void {
    $case = $this->createNode([
      'type' => 'case',
      'field_diary_number' => 'HEL-2022-008715',
    ]);

    $this->assertEquals([
      'en' => '/case/hel-2022-008715',
      'fi' => '/asia/hel-2022-008715',
      'sv' => '/arende/hel-2022-008715',
    ], $this->getAliases($case));

    $decision = $this->createNode([
      'type' => 'decision',
      'field_diary_number' => 'HEL-2022-008715',
      'field_decision_native_id' => '{1C7D448B-945E-44D0}',
      'field_unique_id' => 'unique-1',
    ]);

    $this->assertEquals([
      'en' => '/case/hel-2022-008715/1c7d448b-945e-44d0',
      'fi' => '/asia/hel-2022-008715/1c7d448b-945e-44d0',
      'sv' => '/arende/hel-2022-008715/1c7d448b-945e-44d0',
    ], $this->getAliases($decision));

    // Swedish version of the decision is a separate node with its own native
    // id. Its aliases must not collide with the Finnish decision.
    $swedishDecision = $this->createNode([
      'type' => 'decision',
      'langcode' => 'sv',
      'field_diary_number' => 'HEL-2022-008715',
      'field_decision_native_id' => '{223A28F0-778A-400D}',
      'field_unique_id' => 'unique-1',
    ]);

    $this->assertEquals([
      'en' => '/case/hel-2022-008715/223a28f0-778a-400d',
      'fi' => '/asia/hel-2022-008715/223a28f0-778a-400d',
      'sv' => '/arende/hel-2022-008715/223a28f0-778a-400d',
    ], $this->getAliases($swedishDecision));

    // Decisions without diary number.
    $decisionWithoutCase = $this->createNode([
      'type' => 'decision',
      'field_decision_native_id' => '{2D3DD3D2-6B5A}',
    ]);

    $this->assertEquals([
      'en' => '/case/2d3dd3d2-6b5a',
      'fi' => '/asia/2d3dd3d2-6b5a',
      'sv' => '/arende/2d3dd3d2-6b5a',
    ], $this->getAliases($decisionWithoutCase));

    // Aliases are updated.
    $decision->set('field_decision_native_id', '{AAAA-BBBB}');
    $decision->save();

    $this->assertEquals([
      'en' => '/case/hel-2022-008715/aaaa-bbbb',
      'fi' => '/asia/hel-2022-008715/aaaa-bbbb',
      'sv' => '/arende/hel-2022-008715/aaaa-bbbb',
    ], $this->getAliases($decision));

    // Untranslated nodes get localized URLs in all languages.
    // URL language prefixes are not configured in kernel tests.
    $languageManager = $this->container->get('language_manager');
    $this->assertEquals('/arende/hel-2022-008715/aaaa-bbbb', $decision->toUrl('canonical', [
      'language' => $languageManager->getLanguage('sv'),
    ])->toString());
    $this->assertEquals('/case/hel-2022-008715', $case->toUrl('canonical', [
      'language' => $languageManager->getLanguage('en'),
    ])->toString());

    // Aliases are deleted with the node.
    $decision->delete();
    $this->assertEmpty($this->getAliases($decision));
  }

  /**
   * Tests that manual aliases are not touched.
   */
  public function testManualAlias(): void {
    $decision = $this->createNode([
      'type' => 'decision',
      'field_diary_number' => 'HEL-2022-008715',
      'field_decision_native_id' => '{1C7D448B}',
      'path' => [
        'alias' => '/custom-alias',
        'pathauto' => PathautoState::SKIP,
      ],
    ]);

    $this->assertEquals(['fi' => '/custom-alias'], $this->getAliases($decision));
  }

  /**
   * Tests the backfill command.
   */
  public function testCommand(): void {
    $decision = $this->createNode([
      'type' => 'decision',
      'field_diary_number' => 'HEL-2022-008715',
      'field_decision_native_id' => '{1C7D448B}',
    ]);

    $case = $this->createNode([
      'type' => 'case',
      'field_diary_number' => 'HEL-2022-008715',
    ]);

    // Simulate content created before the aliases were generated.
    $storage = $this->container->get(EntityTypeManagerInterface::class)
      ->getStorage('path_alias');
    $storage->delete($storage->loadByProperties(['langcode' => ['sv', 'en']]));

    $this->assertEquals(['fi' => '/asia/hel-2022-008715/1c7d448b'], $this->getAliases($decision));
    $this->assertEquals(['fi' => '/asia/hel-2022-008715'], $this->getAliases($case));

    $tester = new CommandTester(new GenerateLanguageAliasesCommand(
      $this->container->get(EntityTypeManagerInterface::class),
      $this->container->get(LanguageAliasGenerator::class),
    ));

    // Invalid bundle.
    $this->assertEquals(GenerateLanguageAliasesCommand::INVALID, $tester->execute(['--bundle' => ['article']]));

    $this->assertEquals(GenerateLanguageAliasesCommand::SUCCESS, $tester->execute([
      '--bundle' => ['decision'],
      '--batch-size' => 1,
    ]));
    $this->assertStringContainsString('Created or updated 2 aliases.', $tester->getDisplay());
    $this->assertCount(3, $this->getAliases($decision));
    $this->assertCount(1, $this->getAliases($case));

    $this->assertEquals(GenerateLanguageAliasesCommand::SUCCESS, $tester->execute([]));
    $this->assertStringContainsString('Created or updated 2 aliases.', $tester->getDisplay());
    $this->assertCount(3, $this->getAliases($case));

    // The command is idempotent.
    $this->assertEquals(GenerateLanguageAliasesCommand::SUCCESS, $tester->execute([]));
    $this->assertStringContainsString('Created or updated 0 aliases.', $tester->getDisplay());
  }

}
