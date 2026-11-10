<?php

namespace Drupal\paatokset_ahjo_api\Service;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\paatokset_ahjo_api\Entity\CaseBundle;
use Drupal\paatokset_ahjo_api\Entity\Decision;
use Drupal\paatokset_ahjo_api\Entity\Policymaker;
use Drupal\path_alias\AliasRepositoryInterface;

/**
 * Service class for retrieving case and decision-related data.
 *
 * @package Drupal\paatokset_ahjo_api\Services
 */
class CaseService {

  use StringTranslationTrait;

  /**
   * Machine name for case node type.
   */
  const CASE_NODE_TYPE = 'case';

  /**
   * Machine name for decision node type.
   */
  const DECISION_NODE_TYPE = 'decision';

  /**
   * Memoized default decisions keyed by case id and langcode.
   *
   * @var array<string, \Drupal\paatokset_ahjo_api\Entity\Decision|null>
   */
  private array $defaultDecisions = [];

  /**
   * Creates a new CaseService.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\path_alias\AliasRepositoryInterface $aliasRepository
   *   The path alias repository.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    private readonly LanguageManagerInterface $languageManager,
    private readonly AliasRepositoryInterface $aliasRepository,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * Get default decision for case.
   *
   * Prefers decisions in the current language.
   *
   * @param \Drupal\paatokset_ahjo_api\Entity\CaseBundle $case
   *   Case node.
   *
   * @return \Drupal\paatokset_ahjo_api\Entity\Decision|null
   *   Default (latest) decision entity, if found.
   */
  public function getDefaultDecision(CaseBundle $case): ?Decision {
    $language = $this->languageManager->getCurrentLanguage()->getId();

    $key = $case->id() . ':' . $language;
    if (array_key_exists($key, $this->defaultDecisions)) {
      return $this->defaultDecisions[$key];
    }

    $case_id = $case->getDiaryNumber();
    $nodes = $this->decisionQuery([
      'case_id' => $case_id,
      'limit' => 1,
      'langcode' => $language,
    ]);

    // If there isn't a decision for current language, retry without language.
    if (empty($nodes)) {
      $nodes = $this->decisionQuery([
        'case_id' => $case_id,
        'limit' => 1,
      ]);
    }

    $decision = array_shift($nodes);
    assert($decision === NULL || $decision instanceof Decision);

    return $this->defaultDecisions[$key] = $decision;
  }

  /**
   * Get decision based on Native ID.
   *
   * @param string $decision_id
   *   Decision's native ID.
   * @param ?string $case_id
   *   Optional case id for stricter query, maybe pointless?
   *
   * @return \Drupal\node\NodeInterface|null
   *   Decision entity, if found.
   */
  public function getDecision(string $decision_id, ?string $case_id = NULL): ?NodeInterface {
    $query = [
      'decision_id' => $decision_id,
      'limit' => 1,
    ];

    if (!is_null($case_id)) {
      $query['case_id'] = $case_id;
    }

    $nodes = $this->decisionQuery($query);

    if (empty($nodes)) {
      return NULL;
    }

    return reset($nodes);
  }

  /**
   * Get decision node from redirect data.
   *
   * Decision native IDs may change. Redirects are created from the old paths.
   *
   * @param string $case_id
   *   Diary number for decision.
   * @param string $decision_id
   *   Decision native ID.
   *
   * @return \Drupal\node\NodeInterface|null
   *   Decision node, if one can be found based on a redirect.
   */
  public function getDecisionFromRedirect(string $case_id, string $decision_id): ?NodeInterface {
    $case_id = strtolower($case_id);
    $decision_id = strtolower(trim($decision_id, '{}'));

    foreach (['asia', 'arende', 'case'] as $prefix) {
      $node = $this->getNodeFromRedirectSource("$prefix/$case_id/$decision_id");
      if ($node instanceof NodeInterface) {
        return $node;
      }
    }

    return NULL;
  }

  /**
   * Get node by redirect source path.
   *
   * @param string $source_path
   *   Source path to check.
   *
   * @return \Drupal\node\NodeInterface|null
   *   Node, if a redirect is found, and it points directly to entity.
   */
  private function getNodeFromRedirectSource(string $source_path): ?NodeInterface {
    // phpcs:ignore
    if (!\Drupal::hasService('redirect.repository')) {
      return NULL;
    }

    /** @var \Drupal\redirect\RedirectRepository $redirectRepository */
    $redirectRepository = \Drupal::service('redirect.repository'); // phpcs:ignore
    $redirect_entity = $redirectRepository->findBySourcePath($source_path);

    if (empty($redirect_entity)) {
      return NULL;
    }

    $redirect_entity = reset($redirect_entity);
    $redirect = $redirect_entity->getRedirect();
    if (!isset($redirect['uri'])) {
      return NULL;
    }

    $uri = Url::fromUri($redirect['uri']);
    if (!$uri) {
      return NULL;
    }

    $parameters = $uri->getRouteParameters();
    if (!isset($parameters['node'])) {
      return NULL;
    }

    $entity = $this->entityTypeManager
      ->getStorage('node')
      ->load($parameters['node']);

    assert(!$entity || $entity instanceof NodeInterface);
    return $entity;
  }

  /**
   * Get decision URL by native ID.
   *
   * @param string $id
   *   Native ID for decision or motion.
   *
   * @return \Drupal\Core\Url|null
   *   URL for decision or motion, or NULL if not found.
   */
  public function getDecisionUrlByNativeId(string $id): ?Url {
    $node = $this->getDecision($id);

    if ($node instanceof NodeInterface) {
      return $this->getDecisionUrlFromNode($node);
    }

    return NULL;
  }

  /**
   * Get decision URL by version series ID.
   *
   * @param string $id
   *   Version Series ID for decision or motion.
   *
   * @return \Drupal\Core\Url|null
   *   URL for decision or motion, or NULL if not found.
   */
  public function getDecisionUrlByVersionSeriesId(string $id): ?Url {
    $params = [
      'version_id' => $id,
      'limit' => 1,
    ];

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
   * Get decision URL by native ID and case ID without loading the node.
   *
   * @param string $id
   *   Decision native ID.
   * @param string|null $case_id
   *   Decision case ID.
   * @param string|null $langcode
   *   Language of the decision. Defaults to current language.
   *
   * @return \Drupal\Core\Url|null
   *   URL if the decision path alias exists.
   */
  public function getDecisionUrlWithoutNode(string $id, ?string $case_id = NULL, ?string $langcode = NULL): ?Url {
    if ($langcode === NULL) {
      $langcode = $this->languageManager->getCurrentLanguage()->getId();
    }

    $prefix = match ($langcode) {
      'sv' => 'arende',
      'en' => 'case',
      default => 'asia',
    };

    $path = '/' . $prefix;

    if ($case_id) {
      $path .= '/' . strtolower(str_replace(' ', '-', $case_id));
    }

    // Remove brackets and convert to lowercase.
    // See Decision::getNormalizedNativeId.
    $path .= '/' . strtolower(str_replace(['{', '}'], '', $id));

    $alias = $this->aliasRepository->lookupByAlias($path, $langcode);

    // Correct decision can't be found with this method if alias is null.
    if (!$alias || !preg_match('/^\/node\/(\d+)$/', $alias['path'], $matches)) {
      return NULL;
    }

    // Path alias is resolved when the URL is rendered in the current language.
    return Url::fromRoute('entity.node.canonical', ['node' => $matches[1]]);
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
   * @return \Drupal\Core\Url|null
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
   * Get decision URL.
   *
   * Prefers decision version in the requested language. Decisions
   * translations are separate nodes, connected with field_unique_id.
   *
   * @param \Drupal\node\NodeInterface|null $decision
   *   Decision node.
   * @param string|null $langcode
   *   Langcode to get URL for. Defaults to current language.
   *
   * @return \Drupal\Core\Url|null
   *   Decision URL.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function getDecisionUrlFromNode(?NodeInterface $decision = NULL, ?string $langcode = NULL): ?Url {
    if (!$decision instanceof NodeInterface) {
      return NULL;
    }

    $language = $langcode !== NULL
      ? $this->languageManager->getLanguage($langcode)
      : $this->languageManager->getCurrentLanguage();

    try {
      $decision = $this->getDecisionTranslation($decision, $language?->getId());
    }
    catch (\InvalidArgumentException) {
      // Translation does not exist. Display the decision that we have with
      // translated UI. The path is localized by the path processor.
      // @see \Drupal\paatokset_ahjo_api\Routing\DecisionPathProcessor
    }

    return $decision->toUrl('canonical', $language ? ['language' => $language] : []);
  }

  /**
   * Check if given decision has any translations.
   *
   * @param \Drupal\node\NodeInterface $decision
   *   Decision node.
   *
   * @return bool
   *   True if decision has translation
   */
  public function decisionHasTranslations(NodeInterface $decision): bool {
    // Translations are matched using field_unique_id.
    if (!$decision->hasField('field_unique_id') || $decision->get('field_unique_id')->isEmpty()) {
      return FALSE;
    }

    $nids = $this->entityTypeManager->getStorage('node')
      ->getQuery()
      ->condition('type', self::DECISION_NODE_TYPE)
      ->condition('status', 1)
      ->condition('field_unique_id', $decision->get('field_unique_id')->getString())
      // Do not count this node. Also excludes cases that have failed to
      // generate unique id from decision makers that don't create decisions in
      // multiple languages (bug still requires further investigation).
      ->condition('langcode', $decision->language()->getId(), '<>')
      ->accessCheck(FALSE)
      ->count()
      ->execute();

    return $nids > 0;
  }

  /**
   * Get translated version of decision by unique ID.
   *
   * @todo move decision translations to actual translation entities.
   *
   * @param \Drupal\node\NodeInterface $decision
   *   Decision node.
   * @param string|null $langcode
   *   Which language version to get. Defaults to current language.
   *
   * @return \Drupal\node\NodeInterface
   *   Decision node in the specified language.
   *
   * @throws \InvalidArgumentException
   *   Thrown if the translation with given language does not exist.
   */
  public function getDecisionTranslation(NodeInterface $decision, ?string $langcode = NULL): NodeInterface {
    if ($langcode === NULL) {
      $langcode = $this->languageManager->getCurrentLanguage()->getId();
    }

    // If we already have the correct langcode, return the original node.
    if ($decision->get('langcode')->value === $langcode) {
      return $decision;
    }

    // If we can't get unique ID field, throw.
    if ($decision->hasField('field_unique_id') && !$decision->get('field_unique_id')->isEmpty()) {
      // Attempt to get node with same unique ID but correct langcode.
      $node = $this->decisionQuery([
        'unique_id' => $decision->get('field_unique_id')->value,
        'langcode' => $langcode,
      ]);

      // If language version can't be found, return NULL.
      if (!empty($node)) {
        return reset($node);
      }
    }

    throw new \InvalidArgumentException("Translation to {$langcode} for node:{$decision->id()} does not exists");
  }

  /**
   * Format decision label.
   *
   * @param \Drupal\paatokset_ahjo_api\Entity\Decision $node
   *   Decision node.
   * @param \Drupal\paatokset_ahjo_api\Entity\Policymaker $policymaker
   *   Policymaker used in the label.
   *
   * @return string
   *   Formatted label.
   */
  public function formatDecisionLabel(Decision $node, ?Policymaker $policymaker): string {
    // If policymaker node cannot be found, use value from decision node.
    $org_label = $policymaker?->getPolicymakerName() ?? $node->getDecisionMakerOrgName();

    $meeting_number = $node->field_meeting_sequence_number->value;
    if (!$node->get('field_meeting_date')->isEmpty()) {
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
      $label = $node->label() . ' ' . $decision_date;
    }

    return $label;
  }

  /**
   * Get policymakers for multiple decision.
   *
   * This is an optimization to avoid N+1 queries when
   * calling Decision::getPolicymaker() in a loop.
   *
   * @param \Drupal\paatokset_ahjo_api\Entity\Decision[] $decisions
   *   List of decisions.
   *
   * @return array<string, Policymaker>
   *   Policymakers for given decisions keyed by policymaker ids.
   */
  private function loadPolicymakers(array $decisions): array {
    $currentLanguage = $this->languageManager->getCurrentLanguage()->getId();

    // Load all policymakers in as few queries as possible.
    $policymakerIds = array_unique(array_map(static fn (Decision $decision) => $decision->getPolicymakerId(), $decisions));

    if (empty($policymakerIds)) {
      return [];
    }

    $storage = $this->entityTypeManager
      ->getStorage('node');

    $nids = $storage->getQuery()
      ->accessCheck()
      ->condition('type', 'policymaker')
      ->condition('status', 1)
      ->condition('field_policymaker_id', $policymakerIds, 'IN')
      ->execute();

    $policymakers = $storage->loadMultiple($nids);

    $result = [];
    foreach ($policymakers as $policymaker) {
      assert($policymaker instanceof Policymaker);

      if ($policymaker->hasTranslation($currentLanguage)) {
        $policymaker = $policymaker->getTranslation($currentLanguage);
      }

      $result[$policymaker->getPolicymakerId()] = $policymaker;
    }

    return $result;
  }

  /**
   * Get decisions list for dropdown.
   *
   * @param \Drupal\paatokset_ahjo_api\Entity\CaseBundle $case
   *   Case ID. Leave NULL to use active case.
   *
   * @return array
   *   Dropdown contents.
   */
  public function getDecisionsList(CaseBundle $case): array {
    $decisions = $case->getAllDecisions();
    $policymakers = $this->loadPolicymakers($decisions);

    // Link untranslated decisions with the current UI language.
    $language = $this->languageManager->getCurrentLanguage();

    $results = [];
    foreach ($decisions as $node) {
      // Policymakers are loaded beforehand to prevent N+1 query here.
      $policymaker = $policymakers[$node->getPolicymakerId()] ?? NULL;

      $results[] = [
        'id' => $node->id(),
        'unique_id' => $node->field_unique_id->value,
        'langcode' => $node->language()->getId(),
        'native_id' => $node->getNormalizedNativeId(),
        'url' => $node->toUrl('canonical', ['language' => $language])->toString(),
        'title' => $node->label(),
        'organization' => $node->field_dm_org_name->value,
        'organization_type' => $node->field_organization_type->value,
        'label' => $this->formatDecisionLabel($node, $policymaker),
        'class' => Html::cleanCssIdentifier($policymaker?->getPolicymakerClass() ?? 'color-board'),
      ];
    }

    return $results;
  }

  /**
   * Parse Ahjo API HTML main content from motion or content raw data.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Decision node.
   * @param string $field_name
   *   Which field to get raw data from.
   * @param bool $strip_tags
   *   Only allow text related tags, strip images etc.
   *
   * @return string|null
   *   Parsed content HTML as string, if found.
   */
  public function getDecisionContentFromHtml(NodeInterface $node, string $field_name, bool $strip_tags = FALSE): ?string {
    if (!$node instanceof NodeInterface || !$node->hasField($field_name) || $node->get($field_name)->isEmpty()) {
      return NULL;
    }

    $html = $node->get($field_name)->value;
    return $this->parseContentSectionsFromHtml($html, $strip_tags);
  }

  /**
   * Parse content sections from raw HTML.
   *
   * @param string $html
   *   Input HTML.
   * @param bool $strip_tags
   *   Only allow text related tags, strip images etc.
   *
   * @return string|null
   *   Content sections, if found.
   */
  public function parseContentSectionsFromHtml(string $html, bool $strip_tags = FALSE): ?string {
    if (empty($html)) {
      // Fixme: Maybe this should return NULL or throw something. This matches
      // the previous behaviour which I'm afraid to change right now.
      return "";
    }

    $dom = new \DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new \DOMXPath($dom);

    $content = NULL;
    // Main decision content sections.
    $sections = $xpath->query("//*[contains(@class, 'SisaltoSektio')]");

    // If content sections are empty (confidential data), use title instead.
    if ($sections->length === 0) {
      $sections = $xpath->query("//*[contains(@class, 'AsiaOtsikko')]");
    }

    foreach ($sections as $section) {
      $content .= $section->ownerDocument->saveHTML($section);
    }

    if ($strip_tags) {
      // Fixme: Maybe this should return NULL or throw something. This matches
      // the previous behaviour which I'm afraid to change right now.
      if (empty($content)) {
        return "";
      }

      $allowed_tags = [
        'h1',
        'h2',
        'h3',
        'h4',
        'h5',
        'h6',
        'ul',
        'ol',
        'li',
        'p',
      ];
      return strip_tags($content, $allowed_tags);
    }

    return $content;
  }

  /**
   * Get top category name from classification code.
   *
   * @param string $code
   *   Classification code.
   * @param string $langcode
   *   Language to get category name for.
   *
   * @return string|null
   *   Top category name, if found.
   */
  public function getTopCategoryFromClassificationCode(string $code, string $langcode = 'fi'): ?string {
    $bits = explode(' ', (string) $code);
    $code = array_shift($bits);
    $key = $code . '-' . $langcode;

    return match ($key) {
      "00-fi" => "Hallintoasiat",
      "00-sv" => "Förvaltningsärenden",
      "01-fi" => "Henkilöstöasiat",
      "01-sv" => "Personalärenden",
      "02-fi" => "Talousasiat, verotus ja omaisuuden hallinta",
      "02-sv" => "Ekonomi, beskattning och egendomsförvaltning",
      "03-fi" => "Lainsäädäntö ja lainsäädännön soveltaminen",
      "03-sv" => "Lagstiftning och dess tillämpning",
      "04-fi" => "Kansainvälinen toiminta ja maahanmuuttopolitiikka",
      "04-sv" => "Internationell verksamhet och migrationspolitik",
      "05-fi" => "Sosiaalitoimi",
      "05-sv" => "Socialvård",
      "06-fi" => "Terveydenhuolto",
      "06-sv" => "Hälsovård",
      "07-fi" => "Tiedon hallinta",
      "07-sv" => "Informationshantering",
      "08-fi" => "Liikenne",
      "08-sv" => "Trafik",
      "09-fi" => "Turvallisuus ja yleinen järjestys",
      "09-sv" => "Säkerhet och allmän ordning",
      "10-fi" => "Maankäyttö, rakentaminen ja asuminen",
      "10-sv" => "Markanvändning, byggande och boende",
      "11-fi" => "Ympäristöasia",
      "11-sv" => "Miljöärenden",
      "12-fi" => "Opetus- ja sivistystoimi",
      "12-sv" => "Undervisnings- och bildningsväsende",
      "13-fi" => "Tutkimus- ja kehittämistoiminta",
      "13-sv" => "Forskning och utveckling",
      "14-fi" => "Elinkeino- ja työvoimapalvelut",
      "14-sv" => "Näringslivs- och arbetskraftstjänster",
      default => NULL,
    };
  }

  /**
   * Find motion as decision node based on NativeId or VersionSeriesId.
   *
   * @param string $native_id
   *   NativeId for motion PDF document.
   * @param string $version_id
   *   VersionSeriesId for motion PDF document.
   *
   * @return \Drupal\node\NodeInterface|null
   *   Decision node as motion. NULL if not found.
   */
  public function findMotionById(string $native_id, string $version_id): ?NodeInterface {
    // See if node already exists with same NativeId.
    $node = $this->decisionQuery([
      'decision_id' => $native_id,
      'limit' => 1,
    ]);

    $node = reset($node);
    if ($node instanceof NodeInterface) {
      return $node;
    }

    // If not, try with VersionSeriesId.
    $node = $this->decisionQuery([
      'version_id' => $version_id,
      'limit' => 1,
    ]);

    $node = reset($node);
    if ($node instanceof NodeInterface) {
      return $node;
    }

    return NULL;
  }

  /**
   * Find or create motion as decision node based on IDs or meeting data.
   *
   * @param string|null $version_id
   *   VersionSeriesId for motion document.
   * @param string|null $case_id
   *   Diary number for motion. Can be NULL.
   * @param string $meeting_id
   *   Meeting ID for motion.
   * @param string $title
   *   Motion title (used in case there are multiple motions with same id).
   *
   * @return \Drupal\node\NodeInterface|null
   *   Decision node as motion. NULL if not found.
   */
  public function findOrCreateMotionByMeetingData(?string $version_id, ?string $case_id, string $meeting_id, string $title): ?NodeInterface {
    $nodes = $this->decisionQuery([
      'case_id' => $case_id,
      'meeting_id' => $meeting_id,
      'version_id' => $version_id,
      'limit' => 1,
    ]);

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
    $params['sort_by'] = 'field_meeting_date';
    return $this->query($params, $load_nodes);
  }

  /**
   * Main query. Can fetch cases and decisions.
   *
   * @param array $params
   *   Parameters for query.
   * @param bool $load_nodes
   *   Load nodes or just return node ids.
   *
   * @return array
   *   List of nodes or node ids.
   */
  private function query(array $params, bool $load_nodes = TRUE): array {
    $sort = $params['sort'] ?? 'DESC';
    $sort_by = $params['sort_by'] ?? 'field_created';

    $storage = $this->entityTypeManager
      ->getStorage('node');

    $query = $storage
      ->getQuery()
      ->accessCheck(TRUE)
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
      $query->condition('field_decision_native_id', Decision::bracketNativeId($params['decision_id']));
    }

    if (isset($params['version_id'])) {
      $query->condition('field_decision_series_id', Decision::bracketNativeId($params['version_id']));
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

    return $storage->loadMultiple($ids);
  }

}
