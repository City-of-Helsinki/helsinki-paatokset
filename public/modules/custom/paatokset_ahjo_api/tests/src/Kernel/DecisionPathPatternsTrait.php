<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel;

use Drupal\pathauto\Entity\PathautoPattern;

/**
 * Creates the case and decision pathauto patterns used by the site.
 *
 * The patterns are exported to conf/cmi and are not available in tests.
 * Requires 'language', 'pathauto', 'path_alias' and 'token' modules.
 */
trait DecisionPathPatternsTrait {

  /**
   * Create case and decision pathauto patterns.
   */
  protected function createDecisionPathPatterns(): void {
    $prefixes = ['fi' => 'asia', 'sv' => 'arende', 'en' => 'case'];

    foreach ($prefixes as $langcode => $prefix) {
      $suffix = $langcode === 'fi' ? '' : "_$langcode";

      $this->createDecisionPathPattern("case_pattern$suffix", 'case', $langcode, "/$prefix/[node:field_diary_number]");
      $this->createDecisionPathPattern("decision_pattern$suffix", 'decision', $langcode, "/$prefix/[node:field_diary_number]/[node:field_decision_native_id]");
    }
  }

  /**
   * Create a pathauto pattern with bundle and language conditions.
   */
  private function createDecisionPathPattern(string $id, string $bundle, string $langcode, string $pattern): void {
    $entity = PathautoPattern::create([
      'id' => $id,
      'label' => $id,
      'type' => 'canonical_entities:node',
      'pattern' => $pattern,
      'weight' => -10,
      'selection_logic' => 'and',
      'relationships' => [
        'node:langcode:language' => ['label' => 'Language'],
      ],
    ]);

    $entity->addSelectionCondition([
      'id' => 'entity_bundle:node',
      'bundles' => [$bundle => $bundle],
      'negate' => FALSE,
      'context_mapping' => ['node' => 'node'],
    ]);

    $entity->addSelectionCondition([
      'id' => 'language',
      'langcodes' => [$langcode => $langcode],
      'negate' => FALSE,
      'context_mapping' => ['language' => 'node:langcode:language'],
    ]);

    $entity->save();
  }

}
