<?php

namespace Drupal\paatokset_ahjo_api\Service;

use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\Component\Utility\Html;

/**
 * Service class for retrieving case and decision-related data.
 *
 * @package Drupal\paatokset_ahjo_api\Services
 */
class CaseService {
  /**
   * Machine name for case node type.
   */
  const CASE_NODE_TYPE = 'case';

  /**
   * Machine name for decision node type.
   */
  const DECISION_NODE_TYPE = 'decision';

  /**
   * Machine name for meeting document media type.
   */
  const DOCUMENT_TYPE = 'ahjo_document';

  /**
   * Case node.
   *
   * @var \Drupal\node\Entity\Node
   */
  private $case;

  /**
   * Decision node.
   *
   * @var \Drupal\node\Entity\Node
   */
  private $selectedDecision;

  /**
   * Case diary number.
   *
   * @var string
   */
  private $caseId;

  /**
   * Set case and decision entities based on path.
   */
  public function setEntitiesByPath(): void {
    $entityTypeIndicator = \Drupal::routeMatch()->getParameters()->keys()[0];
    $case = \Drupal::routeMatch()->getParameter($entityTypeIndicator);
    if ($case instanceof NodeInterface && $case->bundle() === self::CASE_NODE_TYPE) {
      $this->case = $case;
    }

    $this->caseId = $case->get('field_diary_number')->value;

    $decision_id = \Drupal::request()->query->get('decision');
    if (!$decision_id) {
      $this->selectedDecision = $this->getDefaultDecision($this->caseId);
    }
    else {
      $this->selectedDecision = $this->getDecision($decision_id);
    }
  }

  /**
   * Set case and decision entities based on IDs.
   *
   * @param string|null $case_id
   *   Case diary number or NULL if decision doesn't have a case.
   * @param string $decision_id
   *   Decision native ID.
   */
  public function setEntitiesById(?string $case_id = NULL, string $decision_id): void {
    if ($case_id !== NULL) {
      $case_nodes = $this->caseQuery([
        'case_id' => $case_id,
        'limit' => 1,
      ]);
      $this->case = array_shift($case_nodes);
      $this->caseId = $case_id;
    }

    $decision_nodes = $this->decisionQuery([
      'case_id' => $case_id,
      'decision_id' => $decision_id,
      'limit' => 1,
    ]);
    $this->selectedDecision = array_shift($decision_nodes);
  }

  /**
   * Get default decision for case.
   *
   * @param string $case_id
   *   Case diary number.
   *
   * @return Drupal\node\NodeInterface|null
   *   Default (latest) decision entity, if found.
   */
  private function getDefaultDecision(string $case_id): ?NodeInterface {
    $nodes = $this->decisionQuery([
      'case_id' => $case_id,
      'limit' => 1,
    ]);
    return array_shift($nodes);
  }

  /**
   * Get active decision, if set.
   *
   * @return Drupal\node\NodeInterface|null
   *   Active decision entity.
   */
  public function getSelectedDecision(): ?NodeInterface {
    return $this->selectedDecision;
  }

  /**
   * Get decision based on Native ID.
   *
   * @param string $decision_id
   *   Decision's native ID.
   *
   * @return Drupal\node\NodeInterface|null
   *   Decision entity, if found.
   */
  public function getDecision(string $decision_id): ?NodeInterface {
    $decision_nodes = $this->decisionQuery([
      'decision_id' => $decision_id,
      'limit' => 1,
    ]);
    return array_shift($decision_nodes);
  }

  /**
   * Get page main heading either from case or decision node.
   *
   * @return string|null
   *   Main heading or NULL if neither case or decision have been set.
   */
  public function getDecisionHeading(): ?string {
    if ($this->case instanceof NodeInterface && $this->case->hasField('field_full_title') && !$this->case->get('field_full_title')->isEmpty()) {
      return $this->case->get('field_full_title')->value;
    }

    if (!$this->selectedDecision instanceof NodeInterface) {
      return NULL;
    }

    if ($this->selectedDecision->hasField('field_decision_case_title') && !$this->selectedDecision->get('field_decision_case_title')->isEmpty()) {
      return $this->selectedDecision->get('field_decision_case_title')->value;
    }

    if ($this->selectedDecision->hasField('field_full_title') && !$this->selectedDecision->get('field_full_title')->isEmpty()) {
      return $this->selectedDecision->get('field_full_title')->value;
    }

    return $this->selectedDecision->title->value;
  }

  /**
   * Get decision PDF file.
   *
   * @param string|null $decision_id
   *   Decision ID. Leave NULL to use active decision.
   *
   * @return string|null
   *   URL for PDF.
   */
  public function getDecisionPdf(): ?string {
    if (!$this->selectedDecision instanceof NodeInterface || !$this->selectedDecision->hasField('field_decision_record') || $this->selectedDecision->get('field_decision_record')->isEmpty()) {
      return NULL;
    }

    $data = json_decode($this->selectedDecision->get('field_decision_record')->value, TRUE);
    if (!empty($data) && isset($data['FileURI'])) {
      return $data['FileURI'];
    }
    return NULL;
  }

  /**
   * Get label for decision (organization name + date).
   *
   * @param string|null $decision_id
   *   Decision ID. Leave NULL to use active decision.
   *
   * @return string|null
   *   Decision label.
   */
  public function getDecisionLabel(?string $decision_id = NULL): ?string {
    if (!$decision_id) {
      $decision = $this->selectedDecision;
    }
    else {
      $decision = $this->getDecision($decision_id);
    }

    if (!$decision instanceof NodeInterface) {
      return NULL;
    }

    return $this->formatDecisionLabel($decision);
  }

  /**
   * Format decision label.
   *
   * @param Drupal\node\NodeInterface $node
   *   Decision node.
   *
   * @return string
   *   Formatted label.
   */
  private function formatDecisionLabel(NodeInterface $node): string {
    $meeting_number = $node->field_meeting_sequence_number->value;
    $decision_timestamp = strtotime($node->field_decision_date->value);
    $decision_date = date('d.m.Y', $decision_timestamp);

    if ($meeting_number) {
      $decision_date = $meeting_number . '/' . $decision_date;
    }

    if ($node->field_dm_org_name->value) {
      $label = $node->field_dm_org_name->value . ' ' . $decision_date;
    }
    else {
      $label = $node->title->value;
    }

    return $label;
  }

  /**
   * Get CSS class based on decision organization type.
   *
   * @param string|null $decision_id
   *   Decision ID. Leave NULL to use active decision.
   *
   * @return string
   *   CSS class based on org type.
   */
  public function getDecisionClass(?string $decision_id = NULL): string {
    if (!$decision_id) {
      $decision = $this->selectedDecision;
    }
    else {
      $decision = $this->getDecision($decision_id);
    }

    if (!$decision instanceof NodeInterface || !$decision->hasField('field_policymaker_id') || $decision->get('field_policymaker_id')->isEmpty()) {
      return 'color-sumu';
    }

    /** @var \Drupal\paatokset_policymakers\Service\PolicymakerService $policymakerService */
    $policymakerService = \Drupal::service('paatokset_policymakers');

    $class = $policymakerService->getPolicymakerClassById($decision->field_policymaker_id->value);

    return Html::cleanCssIdentifier($class);
  }

  /**
   * Get attachments for active decision.
   *
   * @return array
   *   Array of links.
   */
  public function getAttachments(): array {
    if (!$this->selectedDecision instanceof NodeInterface || !$this->selectedDecision->hasField('field_decision_attachments')) {
      return [];
    }

    $attachments = [];
    foreach ($this->selectedDecision->get('field_decision_attachments') as $field) {

      $data = json_decode($field->value, TRUE);
      $attachments[] = [
        'file_url' => $data['FileURI'],
        'title' => $data['Title'],
      ];
    }

    return $attachments;
  }

  /**
   * Get all decisions for case.
   *
   * @param string|null $case_id
   *   Case ID. Leave NULL to use active case.
   *
   * @return array
   *   Array of decision nodes.
   */
  public function getAllDecisions(?string $case_id = NULL): array {
    if (!$case_id) {
      $case_id = $this->caseId;
    }

    if ($case_id === NULL) {
      return [];
    }

    return $this->decisionQuery(['case_id' => $case_id]);
  }

  /**
   * Get decisions list for dropdown.
   *
   * @param string|null $case_id
   *   Case ID. Leave NULL to use active case.
   *
   * @return array
   *   Dropdown contents.
   */
  public function getDecisionsList(?string $case_id = NULL): array {
    /** @var \Drupal\paatokset_policymakers\Service\PolicymakerService $policymakerService */
    $policymakerService = \Drupal::service('paatokset_policymakers');

    if (!$case_id) {
      $case_id = $this->caseId;
    }
    $decisions = $this->getAllDecisions($case_id);

    $results = [];
    foreach ($decisions as $node) {
      $label = $this->formatDecisionLabel($node);

      if ($node->field_policymaker_id->value) {
        $class = Html::cleanCssIdentifier($policymakerService->getPolicymakerClassById($node->field_policymaker_id->value));
      }
      else {
        $class = 'color-sumu';
      }

      $results[] = [
        'id' => $node->id(),
        'native_id' => $node->field_decision_native_id->value,
        'title' => $node->title->value,
        'organization' => $node->field_dm_org_name->value,
        'organization_type' => $node->field_organization_type->value,
        'label' => $label,
        'class' => $class,
      ];
    }

    return $results;
  }

  /**
   * Get next decision in list, if one exists.
   *
   * @param string|null $case_id
   *   Case ID. Leave NULL to use active case.
   * @param string|null $decision_nid
   *   Decision native ID. Leave NULL to use active selection.
   *
   * @return array|null
   *   ID and title of next decision in list.
   */
  public function getNextDecision(?string $case_id = NULL, ?string $decision_nid = NULL): ?array {
    return $this->getAdjacentDecision(-1, $case_id, $decision_nid);
  }

  /**
   * Get previous decision in list, if one exists.
   *
   * @param string|null $case_id
   *   Case ID. Leave NULL to use active case.
   * @param string|null $decision_nid
   *   Decision native ID. Leave NULL to use active selection.
   *
   * @return array|null
   *   ID and title of previous decision in list.
   */
  public function getPrevDecision(?string $case_id = NULL, ?string $decision_nid = NULL): ?array {
    return $this->getAdjacentDecision(1, $case_id, $decision_nid);
  }

  /**
   * Get adjacent decision in list, if one exists.
   *
   * @param int $offset
   *   Which offset to use (1 for previous, -1 for next, etc).
   * @param string|null $case_id
   *   Case ID. Leave NULL to use active case.
   * @param string|null $decision_nid
   *   Decision native ID. Leave NULL to use active selection.
   *
   * @return array|null
   *   ID and title of adjacent decision in list.
   */
  private function getAdjacentDecision(int $offset, ?string $case_id = NULL, ?string $decision_nid = NULL): ?array {
    if (!$case_id) {
      $case_id = $this->caseId;
    }

    if (!$decision_nid) {
      $decision_nid = $this->selectedDecision->id();
    }

    $all_decisions = $this->getAllDecisions($case_id);
    $all_nids = array_keys($all_decisions);
    $next_nid = NULL;
    foreach ($all_nids as $key => $id) {
      if ((string) $id !== $decision_nid) {
        continue;
      }

      if (isset($all_nids[$key + $offset])) {
        $next_nid = (string) $all_nids[$key + $offset];
      }
      break;
    }

    if (!isset($all_decisions[$next_nid])) {
      return [];
    }

    $node = $all_decisions[$next_nid];

    return [
      'title' => $node->title->value,
      'id' => urlencode($node->field_decision_native_id->value),
    ];
  }

  /**
   * Query for case nodes.
   *
   * @param array $params
   *   Parameters for query.
   *
   * @return array
   *   List of case nodes.
   */
  public function caseQuery(array $params): array {
    $params['type'] = self::CASE_NODE_TYPE;
    return $this->query($params);
  }

  /**
   * Query for decision nodes.
   *
   * @param array $params
   *   Parameters for query.
   *
   * @return array
   *   List of decision nodes.
   */
  public function decisionQuery(array $params): array {
    $params['type'] = self::DECISION_NODE_TYPE;
    $params['sort_by'] = 'field_decision_date';
    return $this->query($params);
  }

  /**
   * Main query. Can fetch cases and decisions.
   *
   * @param array $params
   *   Parameters for query.
   *
   * @return array
   *   List of nodes.
   */
  private function query(array $params): array {
    if (isset($params['sort'])) {
      $sort = $params['sort'];
    }
    else {
      $sort = 'DESC';
    }

    if (isset($params['sort_by'])) {
      $sort_by = $params['sort_by'];
    }
    else {
      $sort_by = 'field_meeting_date';
    }

    $query = \Drupal::entityQuery('node')
      ->condition('status', 1)
      ->condition('type', $params['type'])
      ->sort($sort_by, $sort);

    if (isset($params['limit'])) {
      $query->range('0', $params['limit']);
    }

    if (isset($params['case_id'])) {
      $query->condition('field_diary_number', $params['case_id']);
    }

    if (isset($params['decision_id'])) {
      $query->condition('field_decision_native_id', $params['decision_id']);
    }

    $ids = $query->execute();
    if (empty($ids)) {
      return [];
    }

    return Node::loadMultiple($ids);
  }

}
