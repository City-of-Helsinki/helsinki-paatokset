<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\Condition;

use Drupal\Core\Condition\Attribute\Condition;
use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\paatokset_ahjo_api\Entity\CaseBundle;
use Drupal\paatokset_ahjo_api\Entity\Decision;

/**
 * Checks whether the current node is a case or a decision.
 */
#[Condition(
  id: 'paatokset_case_or_decision',
  label: new TranslatableMarkup('Case or decision'),
  context_definitions: [
    'node' => new EntityContextDefinition(
      data_type: 'entity:node',
      label: new TranslatableMarkup('Node'),
      required: FALSE,
    ),
  ],
)]
final class CaseOrDecision extends ConditionPluginBase {

  /**
   * {@inheritdoc}
   */
  public function evaluate(): bool {
    $node = $this->getContextValue('node');

    return $node instanceof CaseBundle || $node instanceof Decision;
  }

  /**
   * {@inheritdoc}
   */
  public function summary(): TranslatableMarkup {
    return $this->isNegated()
      ? new TranslatableMarkup('The node is not a case or a decision')
      : new TranslatableMarkup('The node is a case or a decision');
  }

}
