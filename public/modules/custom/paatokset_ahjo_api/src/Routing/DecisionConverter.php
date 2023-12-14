<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Routing;

/**
 * Converts decision route parameters to node.
 */
final class DecisionConverter extends DecisionConverterBase {

  /**
   * {@inheritDoc}
   */
  protected string $type = 'decision';

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    if (!empty($value)) {
      // Stricter check if case_id is present.
      $case_id = $defaults['case_id'] ?? NULL;

      return $this->caseService()->getDecision($value, $case_id);
    }

    return NULL;
  }

}
