<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Entity;

use Drupal\node\Entity\Node;

/**
 * Bundle class for decisions.
 */
class CaseBundle extends Node {

  /**
   * Memoized result for self::getCase().
   *
   * @var \Drupal\paatokset_ahjo_api\Entity\Decision[]|null
   */
  private ?array $allDecisions = NULL;

  /**
   * Get diary (case) number.
   */
  public function getDiaryNumber(): string {
    return $this->get('field_diary_number')->getString();
  }

  /**
   * Get all decisions for this case.
   *
   * @return \Drupal\paatokset_ahjo_api\Entity\Decision[]
   *   Array of decision nodes.
   */
  public function getAllDecisions(): array {
    if (isset($this->allDecisions)) {
      return $this->allDecisions;
    }

    $currentLanguage = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $ids = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->getQuery()
      ->accessCheck()
      ->condition('type', 'decision')
      ->condition('status', 1)
      ->condition('field_diary_number', $this->getDiaryNumber())
      ->sort('field_meeting_date')
      ->execute();

    $results = Decision::loadMultiple($ids);

    $native_results = [];
    foreach ($results as $node) {
      // Store all unique IDs for current language decisions.
      if ($node->language()->getId() === $currentLanguage) {
        $native_results[$node->field_unique_id->value] = $results;
      }
    }

    // Remove any decisions where:
    // - The language is not the currently active language.
    // - Another decision with the same ID exists in the active language.
    $results = array_filter($results, fn (Decision $decision) =>
      $decision->language()->getId() === $currentLanguage || !isset($native_results[$decision->field_unique_id->value])
    );

    // Sort decisions by timestamp and then again by section numbering.
    // Has to be done here because query sees sections as text, not numbers.
    usort($results, static function ($item1, $item2) {
      if ($item1->get('field_meeting_date')->isEmpty()) {
        $item1_timestamp = 0;
        $item1_date = NULL;
      }
      else {
        $item1_timestamp = strtotime($item1->get('field_meeting_date')->value);
        $item1_date = date('d.m.Y', $item1_timestamp);
      }
      if ($item2->get('field_meeting_date')->isEmpty()) {
        $item2_timestamp = 0;
        $item2_date = NULL;
      }
      else {
        $item2_timestamp = strtotime($item2->get('field_meeting_date')->value);
        $item2_date = date('d.m.Y', $item2_timestamp);
      }
      if ($item1->get('field_decision_section')->isEmpty()) {
        $item1_section = 0;
      }
      else {
        $item1_section = (int) $item1->get('field_decision_section')->value;
      }
      if ($item2->get('field_decision_section')->isEmpty()) {
        $item2_section = 0;
      }
      else {
        $item2_section = (int) $item2->get('field_decision_section')->value;
      }

      if ($item1_date === $item2_date) {
        return $item2_section - $item1_section;
      }

      return $item2_timestamp - $item1_timestamp;
    });

    return $this->allDecisions = $results;
  }

  /**
   * Get next decision, if one exists.
   *
   * @param \Drupal\paatokset_ahjo_api\Entity\Decision $decision
   *   Current decision.
   *
   * @return \Drupal\paatokset_ahjo_api\Entity\Decision|null
   *   Next decision in list.
   */
  public function getNextDecision(Decision $decision): ?Decision {
    return $this->getAdjacentDecision(-1, $decision);
  }

  /**
   * Get previous decision, if one exists.
   *
   * @param \Drupal\paatokset_ahjo_api\Entity\Decision $decision
   *   Current decision.
   *
   * @return \Drupal\paatokset_ahjo_api\Entity\Decision|null
   *   Previous decision in list.
   */
  public function getPrevDecision(Decision $decision): ?Decision {
    return $this->getAdjacentDecision(1, $decision);
  }

  /**
   * Get adjacent decision in list, if one exists.
   *
   * @param int $offset
   *   Which offset to use (1 for previous, -1 for next).
   * @param \Drupal\paatokset_ahjo_api\Entity\Decision $decision
   *   Current decision.
   *
   * @return Decision|null
   *   Adjacent decision in list.
   */
  private function getAdjacentDecision(int $offset, Decision $decision): ?Decision {
    $all_decisions = array_values($this->getAllDecisions());
    $found_node = NULL;
    foreach ($all_decisions as $key => $value) {
      if ((string) $value->id() !== $decision->id()) {
        continue;
      }

      if (isset($all_decisions[$key + $offset])) {
        $found_node = $all_decisions[$key + $offset];
      }
      break;
    }

    return $found_node;
  }

}
