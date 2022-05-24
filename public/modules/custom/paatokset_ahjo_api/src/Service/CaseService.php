<?php

namespace Drupal\paatokset_ahjo_api\Service;

use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Url;
use Drupal\Component\Utility\Unicode;

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
   * Language ID.
   *
   * @var string
   */
  private $language;

  /**
   * Decision query string key.
   *
   * @var string
   */
  private $decisionQueryKey;

  /**
   * Creates a new CaseService.
   */
  public function __construct() {
    $this->language = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $this->decisionQueryKey = $this->getDecisionQueryKey($this->language);
  }

  /**
   * Get decision query key.
   *
   * @param string|null $langcode
   *   Langcode to use. If NULL, use current language.
   *
   * @return string
   *   Decision query key.
   */
  public function getDecisionQueryKey(?string $langcode = NULL): string {
    if ($langcode === NULL) {
      $langcode = $this->language;
    }

    switch ($langcode) {
      case 'sv':
        $value = 'beslut';
        break;

      case 'en':
        $value = 'decision';
        break;

      default:
        $value = 'paatos';
        break;
    }

    return $value;
  }

  /**
   * Set case and decision entities based on path. Only works on case paths!
   */
  public function setEntitiesByPath(): void {
    $entityTypeIndicator = \Drupal::routeMatch()->getParameters()->keys()[0];
    $case = \Drupal::routeMatch()->getParameter($entityTypeIndicator);

    // For custom routes we just get the ID from the route parameter.
    if (!$case instanceof NodeInterface) {
      $node = $this->caseQuery([
        'case_id' => $case,
        'limit' => 1,
      ]);

      if (!empty($node)) {
        $case = reset($node);
      }
    }

    if ($case instanceof NodeInterface && $case->bundle() === self::CASE_NODE_TYPE) {
      $this->case = $case;
    }

    $this->caseId = $case->get('field_diary_number')->value;

    $decision_id = \Drupal::request()->query->get($this->decisionQueryKey);
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
   * Get active case, if set.
   *
   * @return Drupal\node\NodeInterface|null
   *   Active case entity.
   */
  public function getSelectedCase(): ?NodeInterface {
    return $this->case;
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
      'langcode' => $this->language,
    ]);

    // If there isn't a decision for current language, retry without language.
    if (empty($nodes)) {
      $nodes = $this->decisionQuery([
        'case_id' => $case_id,
        'limit' => 1,
      ]);
    }
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
    // Remove corrupted or otherwise garbled characters from ID.
    $decision_id = preg_replace('/[^\pL\pN\pP\pS\pZ]/u', '', $decision_id);

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
   * Get active decision's PDF file.
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
   * Get policy maker URL for selected decision.
   *
   * @return \Drupal\Core\Url|null
   *   Policy maker URL, if found.
   */
  public function getPolicymakerDecisionsLink(): ?Url {
    if (!$this->selectedDecision instanceof NodeInterface) {
      return NULL;
    }

    if (!$this->selectedDecision->hasField('field_policymaker_id') || $this->selectedDecision->get('field_policymaker_id')->isEmpty()) {
      return NULL;
    }

    $policymaker_id = $this->selectedDecision->get('field_policymaker_id')->value;

    /** @var \Drupal\paatokset_policymakers\Service\PolicymakerService $policymakerService */
    $policymakerService = \Drupal::service('paatokset_policymakers');
    return $policymakerService->getDecisionsRoute($policymaker_id);
  }

  /**
   * Get meeting URL for selected decision.
   *
   * @return \Drupal\Core\Url|null
   *   Meeting URL, if found.
   */
  public function getDecisionMeetingLink(): ?Url {
    if (!$this->selectedDecision instanceof NodeInterface) {
      return NULL;
    }

    // Check if decision has meeting ID.
    if (!$this->selectedDecision->hasField('field_meeting_id') || $this->selectedDecision->get('field_meeting_id')->isEmpty()) {
      return NULL;
    }
    $meeting_id = $this->selectedDecision->get('field_meeting_id')->value;

    // Check if decision has policymaker ID.
    if (!$this->selectedDecision->hasField('field_policymaker_id') || $this->selectedDecision->get('field_policymaker_id')->isEmpty()) {
      return NULL;
    }
    $policymaker_id = $this->selectedDecision->get('field_policymaker_id')->value;

    /** @var \Drupal\paatokset_policymakers\Service\PolicymakerService $policymakerService */
    $policymakerService = \Drupal::service('paatokset_policymakers');
    return $policymakerService->getMinutesRoute($meeting_id, $policymaker_id);
  }

  /**
   * Get decision URL by title and section.
   *
   * @param string $title
   *   Decision title.
   * @param string $meeting_id
   *   Meeting ID, to differentiate between multiple same titles.
   * @param string|null $section
   *   Decision section, if set, to differentiate between multiple same titles.
   *
   * @return Drupal\Core\Url|null
   *   Decision URL, if found.
   */
  public function getDecisionUrlByTitle(string $title, string $meeting_id, ?string $section = NULL): ?Url {
    $params = [
      'title' => $title,
      'meeting_id' => $meeting_id,
      'limit' => 1,
    ];

    if ($section) {
      $params['section'] = $section;
    }

    $nodes = $this->decisionQuery($params);
    if (empty($nodes)) {
      return NULL;
    }

    $node = array_shift($nodes);
    if ($node instanceof NodeInterface) {
      return $this->getDecisionUrlFromNode($node);
    }

    return NULL;
  }

  /**
   * Get voting results as array for selected decision.
   *
   * @return array|null
   *   Array with voting results or NULL.
   */
  public function getVotingResults(): ?array {
    if (!$this->selectedDecision instanceof NodeInterface) {
      return NULL;
    }

    if (!$this->selectedDecision->hasField('field_voting_results') || $this->selectedDecision->get('field_voting_results')->isEmpty()) {
      return NULL;
    }

    $vote_results = [];
    $not_formatted = $this->selectedDecision->get('field_voting_results');
    $types = ['Ayes', 'Noes', 'Blank', 'Absent'];

    foreach ($not_formatted as $row) {
      $grouped_by_party = [];
      $results = [];
      $json = json_decode($row->value);
      foreach ($types as $type) {

        if (empty($json->{$type})) {
          continue;
        }

        // Set accordion for each vote type.
        $results[$type] = $json->{$type};

        if (empty($json->{$type}->Voters)) {
          continue;
        }

        // Collate votes by council group and type.
        foreach ($json->{$type}->Voters as $voter) {
          if (!isset($grouped_by_party[$voter->CouncilGroup])) {
            $grouped_by_party[$voter->CouncilGroup] = [
              'Name' => $voter->CouncilGroup,
              'Ayes' => 0,
            ];
          }
          if (!isset($grouped_by_party[$voter->CouncilGroup][$type])) {
            $grouped_by_party[$voter->CouncilGroup][$type] = 1;
          }
          else {
            $grouped_by_party[$voter->CouncilGroup][$type]++;
          }
        }
      }

      usort($grouped_by_party, function ($a, $b) {
        return strcmp($a['Name'], $b['Name']);
      });

      usort($grouped_by_party, function ($a, $b) {
        return $b['Ayes'] - $a['Ayes'];
      });

      $vote_results[] = [
        'accordions' => $results,
        'by_party' => $grouped_by_party,
      ];
    }

    return $vote_results;
  }

  /**
   * Get localized case URL from node.
   *
   * @param \Drupal\node\NodeInterface|null $case
   *   Case node, or default.
   * @param string|null $langcode
   *   Langcode to get URL for. Defaults to current language.
   *
   * @return \Drupal\Core\Url|null
   *   Localized URL, if found.
   */
  public function getCaseUrlFromNode(?NodeInterface $case = NULL, ?string $langcode = NULL): ?Url {
    if ($case === NULL) {
      $case = $this->case;
    }

    if (!$case instanceof NodeInterface) {
      return NULL;
    }

    if ($langcode === NULL) {
      $langcode === $this->language;
      $strict_lang = FALSE;
    }
    // If langcode is set, we wan't that localized URL specifically or nothing.
    else {
      $strict_lang = TRUE;
    }

    $localizedRoute = 'paatokset_case.' . $langcode;
    if ($this->routeExists($localizedRoute)) {
      $case_url = Url::fromRoute($localizedRoute, ['case_id' => $case->get('field_diary_number')->value]);
    }
    // If langcode is set, we don't want an URL without a localized route.
    elseif ($strict_lang) {
      return NULL;
    }
    // If route doesn't exist, just use case URL.
    else {
      $case_url = $case->toUrl();
    }

    // Get decision ID from selected decision.
    $decision_id = NULL;
    if ($this->selectedDecision instanceof NodeInterface) {
      $decision = $this->getDecisionTranslation($this->selectedDecision, $langcode);
      $decision_id = $decision->get('field_decision_native_id')->value;
    }

    if ($decision_id !== NULL) {
      $case_url->setOption('query', [$this->getDecisionQueryKey($langcode) => $decision_id]);
    }

    return $case_url;
  }

  /**
   * Get localized decision URL from node.
   *
   * @param Drupal\node\NodeInterface|null $decision
   *   Decision node, or default.
   * @param string|null $langcode
   *   Langcode to get URL for. Defaults to current language.
   * @param bool $get_translation
   *   Get translated decision via unique ID field.
   *
   * @return Drupal\Core\Url|null
   *   URL for case node with decision ID as parameter, or decision URL.
   */
  public function getDecisionUrlFromNode(?NodeInterface $decision = NULL, ?string $langcode = NULL, bool $get_translation = FALSE): ?Url {
    // Which language to get URL for.
    if ($langcode === NULL) {
      $langcode = $this->language;
      $strict_lang = FALSE;
    }
    // If langcode is set, we wan't that localized URL specifically or nothing.
    else {
      $strict_lang = TRUE;
    }

    if ($decision === NULL) {
      $decision = $this->selectedDecision;
    }

    if (!$decision instanceof NodeInterface) {
      return NULL;
    }

    if (!$decision->hasField('field_decision_native_id') || $decision->get('field_decision_native_id')->isEmpty()) {
      return $decision->toUrl();
    }

    // Special fallback for decisions without diary numbers.
    if (!$decision->hasField('field_diary_number') || $decision->get('field_diary_number')->isEmpty()) {
      $localizedRoute = 'paatokset_case.' . $langcode;
      if ($this->routeExists($localizedRoute)) {
        $decision_id = \Drupal::service('pathauto.alias_cleaner')->cleanString($decision->get('field_decision_native_id')->value);
        $case_url = Url::fromRoute($localizedRoute, ['case_id' => $decision_id]);
        return $case_url;
      }
      return NULL;
    }

    $decision = $this->getDecisionTranslation($decision, $langcode);

    $decision_id = $decision->get('field_decision_native_id')->value;

    $case = NULL;
    $case = $this->caseQuery([
      'case_id' => $decision->get('field_diary_number')->value,
      'limit' => 1,
    ]);
    $case = array_shift($case);

    // If a case exists, use case route with query parameter.
    if ($case instanceof NodeInterface) {
      // Try to get localized route if one exists for current language.
      $localizedRoute = 'paatokset_case.' . $langcode;
      if ($this->routeExists($localizedRoute)) {
        $case_url = Url::fromRoute($localizedRoute, ['case_id' => $decision->get('field_diary_number')->value]);
      }
      // If langcode is set, we don't want an URL without a localized route.
      elseif ($strict_lang) {
        return NULL;
      }
      // If route doesn't exist, just use case URL.
      else {
        $case_url = $case->toUrl();
      }

      $case_url->setOption('query', [$this->getDecisionQueryKey($langcode) => $decision_id]);
      return $case_url;
    }

    // If case node doesn't exist, try to get localized route for decision.
    $localizedRoute = 'paatokset_decision.' . $langcode;
    if ($this->routeExists($localizedRoute)) {
      $native_id_url = \Drupal::service('pathauto.alias_cleaner')->cleanString($decision->get('field_decision_native_id')->value);
      return Url::fromRoute($localizedRoute, [
        'case_id' => $decision->get('field_diary_number')->value,
        'decision_id' => $native_id_url,
      ]);
    }

    // If langcode is set, we don't want an URL without a localized route.
    if ($strict_lang) {
      return NULL;
    }

    // If route isn't localized for current language return decision's URL.
    return $decision->toUrl();
  }

  /**
   * Get translated version of decision by unique ID.
   *
   * @param \Drupal\node\NodeInterface $decision
   *   Decision node.
   * @param string|null $langcode
   *   Which language version to get, or default.
   *
   * @return \Drupal\node\NodeInterface
   *   Translated or original decision node.
   */
  public function getDecisionTranslation(NodeInterface $decision, ?string $langcode = NULL): NodeInterface {
    if ($langcode === NULL) {
      $langcode = $this->language;
    }

    // If we already have the correct langcode, return the original node.
    if ($decision->get('langcode')->value === $langcode) {
      return $decision;
    }

    // If we can't get unique ID field, return the original node.
    if (!$decision->hasField('field_unique_id') || $decision->get('field_unique_id')->isEmpty()) {
      return $decision;
    }

    // Attempt to get node with same unique ID but correct langcode.
    $node = $this->decisionQuery([
      'unique_id' => $decision->get('field_unique_id')->value,
      'langcode' => $langcode,
    ]);

    // If language version can't be found, return original node.
    if (empty($node)) {
      return $decision;
    }

    return reset($node);
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
   * Get translated decision org name.
   *
   * @param string|null $decision_id
   *   Decision ID, or use default decision.
   *
   * @return string|null
   *   Org name, if found.
   */
  public function getDecisionOrgName(?string $decision_id = NULL): ?string {
    if (!$decision_id) {
      $decision = $this->selectedDecision;
    }
    else {
      $decision = $this->getDecision($decision_id);
    }

    if (!$decision instanceof NodeInterface) {
      return NULL;
    }

    if ($decision->hasField('field_dm_org_name') && !$decision->get('field_dm_org_name')->isEmpty()) {
      $default_name = $decision->get('field_dm_org_name')->value;
    }
    else {
      $default_name = NULL;
    }

    if (!$decision->hasField('field_policymaker_id') || $decision->get('field_policymaker_id')->isEmpty()) {
      return $default_name;
    }

    /** @var \Drupal\paatokset_policymakers\Service\PolicymakerService $policymakerService */
    $policymakerService = \Drupal::service('paatokset_policymakers');
    $policymaker_name = $policymakerService->getPolicymakerNameById($decision->get('field_policymaker_id')->value);
    if (!$policymaker_name) {
      return $default_name;
    }
    return $policymaker_name;
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
    $org_label = NULL;
    if ($node->hasField('field_policymaker_id') && !$node->get('field_policymaker_id')->isEmpty()) {
      /** @var \Drupal\paatokset_policymakers\Service\PolicymakerService $policymakerService */
      $policymakerService = \Drupal::service('paatokset_policymakers');
      $org_label = $policymakerService->getPolicymakerNameById($node->get('field_policymaker_id')->value);
    }

    $meeting_number = $node->field_meeting_sequence_number->value;
    if ($node->hasField('field_meeting_date') && !$node->get('field_meeting_date')->isEmpty()) {
      $decision_timestamp = strtotime($node->field_meeting_date->value);
    }
    else {
      $decision_timestamp = NULL;
    }

    $decision_date = date('d.m.Y', $decision_timestamp);

    if ($meeting_number) {
      $decision_date = $meeting_number . '/' . $decision_date;
    }

    if ($org_label) {
      $label = $org_label . ' ' . $decision_date;
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

    $currentLanguage = $this->language;

    $results = $this->decisionQuery([
      'case_id' => $case_id,
    ]);

    $native_results = [];
    foreach ($results as $node) {
      // Store all unique IDs for current language decisions.
      if ($node->langcode->value === $currentLanguage) {
        $native_results[] = $node->field_unique_id->value;
      }
    }

    // Loop through results again and remove any decisions where:
    // - The language is not the currently active language.
    // - Another decision with the same ID exists in the active language.
    foreach ($results as $key => $node) {
      if ($node->langcode->value !== $currentLanguage && in_array($node->field_unique_id->value, $native_results)) {
        unset($results[$key]);
      }
    }

    return $results;
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

    $currentLanguage = $this->language;

    if (!$case_id) {
      $case_id = $this->caseId;
    }
    $decisions = $this->getAllDecisions($case_id);

    $native_results = [];
    $results = [];
    foreach ($decisions as $node) {
      $label = $this->formatDecisionLabel($node);

      if ($node->field_policymaker_id->value) {
        $class = $policymakerService->getPolicymakerClassById($node->field_policymaker_id->value);
      }
      else {
        $class = 'color-sumu';
      }

      // Store all unique IDs for current langauge decisions.
      if ($node->langcode->value === $currentLanguage) {
        $native_results[] = $node->field_unique_id->value;
      }

      $results[] = [
        'id' => $node->id(),
        'unique_id' => $node->field_unique_id->value,
        'langcode' => $node->langcode->value,
        'native_id' => $node->field_decision_native_id->value,
        'title' => $node->title->value,
        'organization' => $node->field_dm_org_name->value,
        'organization_type' => $node->field_organization_type->value,
        'label' => $label,
        'class' => $class,
      ];
    }

    // Loop through results again and remove any decisions where:
    // - The language is not the currently active language.
    // - Another decision with the same ID exists in the active language.
    foreach ($results as $key => $result) {
      if ($result['langcode'] !== $currentLanguage && in_array($result['unique_id'], $native_results)) {
        unset($results[$key]);
      }
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
    if (!$this->selectedDecision instanceof NodeInterface) {
      return NULL;
    }

    if (!$case_id) {
      $case_id = $this->caseId;
    }

    if (!$decision_nid) {
      $decision_nid = $this->selectedDecision->id();
    }

    $all_decisions = array_values($this->getAllDecisions($case_id));
    $found_node = NULL;
    foreach ($all_decisions as $key => $value) {
      if ((string) $value->id() !== $decision_nid) {
        continue;
      }

      if (isset($all_decisions[$key + $offset])) {
        $found_node = $all_decisions[$key + $offset];
      }
      break;
    }

    if (!$found_node instanceof NodeInterface) {
      return [];
    }

    return [
      'title' => $found_node->title->value,
      'id' => $found_node->field_decision_native_id->value,
    ];
  }

  /**
   * Get formatted section label for decision, including agenda point.
   *
   * @return string|null|Drupal\Core\StringTranslation\TranslatableMarkup
   *   Formatted section label, if possible to generate.
   */
  public function getFormattedDecisionSection(): mixed {
    if (!$this->selectedDecision instanceof NodeInterface) {
      return NULL;
    }

    if (!$this->selectedDecision->hasField('field_decision_section') || $this->selectedDecision->get('field_decision_section')->isEmpty()) {
      return NULL;
    }

    $section = $this->selectedDecision->get('field_decision_section')->value;

    if (!$this->selectedDecision->hasField('field_decision_record') || $this->selectedDecision->get('field_decision_record')->isEmpty()) {
      return '§ ' . $section;
    }

    $data = json_decode($this->selectedDecision->get('field_decision_record')->value, TRUE);

    if (!empty($data) && isset($data['AgendaPoint'])) {
      $section = $section . ' §';
      return t('Case @point. / @section', [
        '@point' => $data['AgendaPoint'],
        '@section' => $section,
      ]);
    }

    return '§ ' . $section;
  }

  /**
   * Check if selected decision's decisionmaker is active.
   *
   * @return bool
   *   Decisionmaker activity status.
   */
  public function decisionPmIsActive(): bool {
    if (!$this->selectedDecision instanceof NodeInterface) {
      return FALSE;
    }

    // Return TRUE if policymaker is not set.
    if (!$this->selectedDecision->hasField('field_policymaker_id') || $this->selectedDecision->get('field_policymaker_id')->isEmpty()) {
      return TRUE;
    }

    $policymaker_id = $this->selectedDecision->get('field_policymaker_id')->value;

    /** @var \Drupal\paatokset_policymakers\Service\PolicymakerService $policymakerService */
    $policymakerService = \Drupal::service('paatokset_policymakers');
    return $policymakerService->policymakerIsActiveById($policymaker_id);
  }

  /**
   * Parse decision content and motion data from HTML.
   *
   * @return array
   *   Render arrays.
   */
  public function parseContent(): array {
    if (!$this->selectedDecision instanceof NodeInterface) {
      return [];
    }

    if ($this->selectedDecision->hasField('field_hide_decision_content') && $this->selectedDecision->get('field_hide_decision_content')->value) {
      $hidden_decisions_text = \Drupal::config('paatokset_ahjo_api.default_texts')->get('hidden_decisions_text.value');
      return [
        'message' => [
          '#prefix' => '<div class="issue__hidden-message">',
          '#suffix' => '</div>',
          '#markup' => $hidden_decisions_text,
        ],
      ];
    }

    if ($this->selectedDecision->hasField('field_diary_number') && !$this->selectedDecision->get('field_diary_number')->isEmpty()) {
      $has_case_id = TRUE;
    }
    else {
      $has_case_id = FALSE;
    }

    $content = $this->selectedDecision->get('field_decision_content')->value;
    $motion = $this->selectedDecision->get('field_decision_motion')->value;

    $content_dom = new \DOMDocument();
    if (!empty($content)) {
      @$content_dom->loadHTML($content);
    }
    $content_xpath = new \DOMXPath($content_dom);

    $motion_dom = new \DOMDocument();
    if (!empty($motion)) {
      @$motion_dom->loadHTML($motion);
    }
    $motion_xpath = new \DOMXPath($motion_dom);

    // If content is not set, use motion html instead.
    // Keep $content variable NULL so we can use that for checking later.
    if (empty($content)) {
      $content_xpath = $motion_xpath;
    }

    $output = [];

    $main_content = NULL;
    // Main decision content sections.
    $content_sections = $content_xpath->query("//*[contains(@class, 'SisaltoSektio')]");

    foreach ($content_sections as $section) {
      $main_content .= $section->ownerDocument->saveHTML($section);
    }

    if ($main_content) {
      $output['main'] = [
        '#markup' => $main_content,
      ];
    }

    // Motion content sections.
    // If decision content is empty, print motion content as main content.
    $motion_sections = $motion_xpath->query("//*[contains(@class, 'SisaltoSektio')]");
    if ($content) {
      $motion_accordions = $this->getMotionSections($motion_sections);
      foreach ($motion_accordions as $accordion) {
        $output['accordions'][] = $accordion;
      }
    }

    // More information.
    $more_info = $content_xpath->query("//*[contains(@class, 'LisatiedotOtsikko')]");
    $more_info_content = NULL;
    if ($more_info) {
      $more_info_content = $this->getHtmlContentUntilBreakingElement($more_info);
      $more_info_content = str_replace(': 310', ': 09 310', $more_info_content);
    }

    if ($more_info_content) {
      $output['more_info'] = [
        'heading' => t('Ask for more info'),
        'content' => ['#markup' => $more_info_content],
      ];
    }

    // Presenter information.
    $presenter_info = $content_xpath->query("//*[contains(@class, 'EsittelijaTiedot')]");
    $presenter_content = NULL;
    if ($presenter_info) {
      $presenter_content = $this->getHtmlContentUntilBreakingElement($presenter_info);
    }

    if ($presenter_content) {
      $output['presenter_info'] = [
        'heading' => t('Presenter information'),
        'content' => ['#markup' => $presenter_content],
      ];
    }

    // Add decision date to appeal process accordion.
    // Do not display for motions, only for decisions.
    $appeal_content = NULL;
    if ($has_case_id && $content && $this->selectedDecision->hasField('field_decision_date') && !$this->selectedDecision->get('field_decision_date')->isEmpty()) {
      $decision_timestamp = strtotime($this->selectedDecision->get('field_decision_date')->value);
      $decision_date = date('d.m.Y', $decision_timestamp);
      $appeal_content = '<p class="issue__decision-date">' . t('This decision was published on <strong>@date</strong>', ['@date' => $decision_date]) . '</p>';
    }

    // Appeal information. Only display for decisions (if content is available).
    $appeal_info = $content_xpath->query("//*[contains(@class, 'MuutoksenhakuOtsikko')]");
    if ($content && $appeal_info) {
      $appeal_content .= $this->getHtmlContentUntilBreakingElement($appeal_info);
    }

    if ($appeal_content) {
      $output['accordions'][] = [
        'heading' => t('Appeal process'),
        'content' => ['#markup' => $appeal_content],
      ];
    }

    return $output;
  }

  /**
   * Parse Ahjo API HTML main content from motion or content raw data.
   *
   * @param Drupal\node\NodeInterface $node
   *   Decision node.
   * @param string $field_name
   *   Which field to get raw data from.
   *
   * @return string|null
   *   Parsed content HTML as string, if found.
   */
  public function getDecisionContentFromHtml(NodeInterface $node, string $field_name): ?string {
    if (!$node instanceof NodeInterface || !$node->hasField($field_name) || $node->get($field_name)->isEmpty()) {
      return NULL;
    }

    $html = $node->get($field_name)->value;
    return $this->parseContentSectionsFromHtml($html);
  }

  /**
   * Parse content sections from raw HTML.
   *
   * @param string $html
   *   Input HTML.
   *
   * @return string|null
   *   Content sections, if found.
   */
  public function parseContentSectionsFromHtml(string $html): ?string {
    $dom = new \DOMDocument();
    if (!empty($html)) {
      @$dom->loadHTML($html);
    }
    $xpath = new \DOMXPath($dom);

    $content = NULL;
    // Main decision content sections.
    $sections = $xpath->query("//*[contains(@class, 'SisaltoSektio')]");

    foreach ($sections as $section) {
      $content .= $section->ownerDocument->saveHTML($section);
    }

    return $content;
  }

  /**
   * Split motions into sections.
   *
   * @param \DOMNodeList $list
   *   Motion content sections.
   *
   * @return array
   *   Array of sections.
   */
  private function getMotionSections(\DOMNodeList $list): array {
    $output = [];
    if ($list->length < 1) {
      return [];
    }

    foreach ($list as $node) {
      if (!$node instanceof \DOMElement) {
        continue;
      }

      $section = ['content' => ['#markup' => NULL]];
      $heading_found = FALSE;
      foreach ($node->childNodes as $node) {
        if (!$heading_found && $node->nodeName === 'h3') {
          $section['heading'] = $node->nodeValue;
          $heading_found = TRUE;
          continue;
        }

        $section['content']['#markup'] .= $node->ownerDocument->saveHtml($node);
      }

      $output[] = $section;
    }

    return $output;
  }

  /**
   * Get HTML content from first heading until next heading.
   *
   * @param \DOMNodeList $list
   *   Xpath query results.
   *
   * @return string|null
   *   HTML content as string, or NULL if content is empty.
   */
  private function getHtmlContentUntilBreakingElement(\DOMNodeList $list): ?string {
    $output = NULL;
    if ($list->length < 1) {
      return NULL;
    }

    $current_item = $list->item(0);
    while ($current_item->nextSibling instanceof \DOMNode) {

      // Iterate over to next sibling. This skips the first one.
      $current_item = $current_item->nextSibling;

      // H3 with a class is considered a breaking element.
      if ($current_item->nodeName === 'h3' && !empty($current_item->getAttribute('class'))) {
        break;
      }

      // Skip over any empty elements.
      if (empty($current_item->nodeValue)) {
        continue;
      }

      $output .= $current_item->ownerDocument->saveHTML($current_item);
    }

    return $output;
  }

  /**
   * Get top category name from classification code.
   *
   * @param string $code
   *   Classification code.
   *
   * @return string|null
   *   Top category name, if found.
   */
  public function getTopCategoryFromClassificationCode(string $code): ?string {
    $bits = explode(' ', (string) $code);
    $code = array_shift($bits);

    switch ($code) {
      case "00":
        $name = "Hallintoasiat";
        break;

      case "01":
        $name = "Henkilöstöasiat";
        break;

      case "02":
        $name = "Talousasiat, verotus ja omaisuuden hallinta";
        break;

      case "03":
        $name = "Lainsäädäntö ja lainsäädännön soveltaminen";
        break;

      case "04":
        $name = "Kansainvälinen toiminta ja maahanmuuttopolitiikka";
        break;

      case "05":
        $name = "Sosiaalitoimi";
        break;

      case "06":
        $name = "Terveydenhuolto";
        break;

      case "07":
        $name = "Tiedon hallinta";
        break;

      case "08":
        $name = "Liikenne";
        break;

      case "09":
        $name = "Turvallisuus ja yleinen järjestys";
        break;

      case "10":
        $name = "Maankäyttö, rakentaminen ja asuminen";
        break;

      case "11":
        $name = "Ympäristöasia";
        break;

      case "12":
        $name = "Opetus- ja sivistystoimi";
        break;

      case "13":
        $name = "Tutkimus- ja kehittämistoiminta";
        break;

      case "14":
        $name = "Elinkeino- ja työvoimapalvelut";
        break;

      default:
        $name = NULL;
        break;
    }

    return $name;
  }

  /**
   * Find or create motion as decision node.
   *
   * @param string|null $case_id
   *   Diary number for motion. Can be NULL.
   * @param string $meeting_id
   *   Meeting ID for motion.
   * @param string $title
   *   Motion title (used in case there are multiple motions with same id).
   * @param bool $check_title
   *   Check if title matches. If not, create new node. FALSE by default.
   *
   * @return Drupal\node\NodeInterface|null
   *   Decision node as motion. NULL if not found or if a decision was found.
   */
  public function findOrCreateMotion(?string $case_id, string $meeting_id, string $title, bool $check_title = FALSE): ?NodeInterface {
    if ($check_title) {
      $nodes = $this->decisionQuery([
        'case_id' => $case_id,
        'meeting_id' => $meeting_id,
        'title' => $title,
      ]);
    }
    else {
      $nodes = $this->decisionQuery([
        'case_id' => $case_id,
        'meeting_id' => $meeting_id,
      ]);
    }

    // If there is only one node, use that.
    $found_node = NULL;
    if (count($nodes) === 1) {
      $found_node = array_shift($nodes);
    }
    // If multiple nodes are found, use title to find correct one.
    else {
      foreach ($nodes as $node) {
        if ($node->field_full_title->value === $title) {
          $found_node = $node;
          break;
        }
      }
    }

    // If node can't be found, create it.
    if (!$found_node instanceof NodeInterface) {
      $found_node = Node::create([
        'type' => 'decision',
        'langcode' => 'fi',
        'title' => Unicode::truncate($title, '255', TRUE, TRUE),
      ]);
    }

    return $found_node;
  }

  /**
   * Query for case nodes.
   *
   * @param array $params
   *   Parameters for query.
   * @param bool $load_nodes
   *   Load nodes or just return nids.
   *
   * @return array
   *   List of case nodes.
   */
  public function caseQuery(array $params, bool $load_nodes = TRUE): array {
    $params['type'] = self::CASE_NODE_TYPE;
    return $this->query($params, $load_nodes);
  }

  /**
   * Query for decision nodes.
   *
   * @param array $params
   *   Parameters for query.
   * @param bool $load_nodes
   *   Load nodes or just return nids.
   *
   * @return array
   *   List of decision nodes or nids.
   */
  public function decisionQuery(array $params, bool $load_nodes = TRUE): array {
    $params['type'] = self::DECISION_NODE_TYPE;
    $params['sort_by'] = 'field_decision_date';
    return $this->query($params, $load_nodes);
  }

  /**
   * Main query. Can fetch cases and decisions.
   *
   * @param array $params
   *   Parameters for query.
   * @param bool $load_nodes
   *   Load nodes or just return nids.
   *
   * @return array
   *   List of nodes or nids.
   */
  private function query(array $params, bool $load_nodes = TRUE): array {
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
      $sort_by = 'field_created';
    }

    $query = \Drupal::entityQuery('node')
      ->condition('status', 1)
      ->condition('type', $params['type'])
      ->sort($sort_by, $sort);

    $query->sort('nid', 'ASC');

    if (isset($params['limit'])) {
      $query->range('0', $params['limit']);
    }

    if (isset($params['langcode'])) {
      $query->condition('langcode', $params['langcode']);
    }

    if (isset($params['title'])) {
      $query->condition('field_full_title', $params['title']);
    }

    if (isset($params['section'])) {
      $query->condition('field_decision_section', $params['section']);
    }

    if (isset($params['meeting_id'])) {
      $query->condition('field_meeting_id', $params['meeting_id']);
    }

    if (isset($params['case_id'])) {
      $query->condition('field_diary_number', $params['case_id']);
    }

    if (isset($params['decision_id'])) {
      $query->condition('field_decision_native_id', $params['decision_id']);
    }

    if (isset($params['unique_id'])) {
      $query->condition('field_unique_id', $params['unique_id']);
    }

    $ids = $query->execute();
    if (empty($ids)) {
      return [];
    }

    if (!$load_nodes) {
      return $ids;
    }

    return Node::loadMultiple($ids);
  }

  /**
   * Check if route exists.
   *
   * @param string $routeName
   *   Route to check.
   *
   * @return bool
   *   If route exists or not.
   */
  public static function routeExists(string $routeName): bool {
    $routeProvider = \Drupal::service('router.route_provider');

    // getRouteByName() throws an exception if route not found.
    try {
      $routeProvider->getRouteByName($routeName);
    }
    catch (\Throwable $throwable) {
      return FALSE;
    }

    return TRUE;
  }

}
