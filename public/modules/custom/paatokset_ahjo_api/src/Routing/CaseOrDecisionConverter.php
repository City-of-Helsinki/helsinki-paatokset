<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Routing;

/**
 * Converts case route parameter to node.
 */
final class CaseOrDecisionConverter extends DecisionConverterBase {

  /**
   * {@inheritDoc}
   */
  protected string $type = 'case_or_decision';

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    if (!empty($value)) {
      return $this->caseService()->getCaseOrDecision($value);
    }

    return NULL;
  }

}
