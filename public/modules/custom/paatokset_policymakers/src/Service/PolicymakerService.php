<?php

declare(strict_types=1);

namespace Drupal\paatokset_policymakers\Service;

use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\Core\Utility\Error;
use Drupal\file\FileInterface;
use Drupal\node\NodeInterface;
use Drupal\paatokset_ahjo_api\Entity\Policymaker;
use Drupal\paatokset_ahjo_api\Service\CaseService;
use Drupal\paatokset_ahjo_api\Service\MeetingService;
use Drupal\paatokset_policymakers\Enum\PolicymakerRoutes;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\pathauto\AliasCleanerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Service class for retrieving policymaker-related data.
 *
 * @package Drupal\paatokset_ahjo_api\Serivces
 */
class PolicymakerService {

  use StringTranslationTrait;

  /**
   * Machine name for meeting node type.
   */
  const NODE_TYPE = 'policymaker';

  /**
   * Cut off date for old meetings.
   */
  const MEETING_START_DATE = '2017-01-01';

  /**
   * Policymaker roles for trustees (not decisionmaker organizations).
   */
  const TRUSTEE_TYPES = ['Viranhaltija', 'Luottamushenkilö'];

  /**
   * Visible trustee roles.
   */
  const TRUSTEE_ROLES = [
    'Jäsen',
    'Varajäsen',
    'Puheenjohtaja',
    'Varapuheenjohtaja',
  ];

  /**
   * City council id in Ahjo.
   */
  public const CITY_COUNCIL_DM_ID = '02900';

  /**
   * City board id in Ahjo.
   */
  public const CITY_BOARD_DM_ID = '00400';

  /**
   * Policymaker node.
   */
  private ?Policymaker $policymaker = NULL;

  /**
   * Policymaker ID.
   *
   * @var string
   */
  private $policymakerId;

  /**
   * Node storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private EntityStorageInterface $nodeStorage;

  /**
   * Meeting service.
   *
   * @var \Drupal\paatokset_ahjo_api\Service\MeetingService
   */
  private MeetingService $meetingService;

  /**
   * Case service.
   *
   * @var \Drupal\paatokset_ahjo_api\Service\CaseService
   */
  private CaseService $caseService;

  /**
   * Constructs policymaker service.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   Language manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   Config service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   Route match service.
   * @param \Drupal\path_alias\AliasManagerInterface $pathAliasManager
   *   Path alias manager.
   * @param \Drupal\pathauto\AliasCleanerInterface $pathAliasCleaner
   *   Path alias cleaner.
   * @param \Drupal\Core\File\FileUrlGenerator $fileUrlGenerator
   *   File URL generator.
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger channel.
   */
  public function __construct(
    private LanguageManagerInterface $languageManager,
    private EntityTypeManagerInterface $entityTypeManager,
    private ConfigFactoryInterface $config,
    private RouteMatchInterface $routeMatch,
    private AliasManagerInterface $pathAliasManager,
    private AliasCleanerInterface $pathAliasCleaner,
    private FileUrlGeneratorInterface $fileUrlGenerator,
    private LoggerInterface $logger,
  ) {
    $this->nodeStorage = $entityTypeManager->getStorage('node');

    // Using dependency injection here fails on `make new` due to cyclical
    // dependency with paatokset_ahjo_api module.
    // phpcs:ignore
    $this->meetingService = \Drupal::service('paatokset_ahjo_meetings');
    // phpcs:ignore
    $this->caseService = \Drupal::service('paatokset_ahjo_cases');
  }

  /**
   * Query policymakers from database.
   *
   * @param array $params
   *   Containing query parameters
   *   $params = [
   *     sort  => (string) ASC or DESC.
   *     limit => (int) Limit results.
   *     policymaker => (string) policymaker ID.
   *   ].
   *
   * @return array
   *   of policymakers.
   */
  public function query(array $params = []) : array {
    $sort = $params['sort'] ?? 'ASC';

    $query = $this->nodeStorage
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 1)
      ->condition('type', self::NODE_TYPE)
      ->sort('created', $sort);

    if (isset($params['limit'])) {
      $query->range('0', $params['limit']);
    }

    if (isset($params['policymaker'])) {
      $query->condition('field_policymaker_id', $params['policymaker']);
    }

    $ids = $query->execute();

    if (empty($ids)) {
      return [];
    }

    return $this->nodeStorage->loadMultiple($ids);
  }

  /**
   * Get policymaker node by ID.
   *
   * @param string|null $id
   *   Policymaker id. Leave null to return current instance.
   * @param string|null $langcode
   *   Translation to get. Leave null to return active translation.
   *
   * @return \Drupal\paatokset_ahjo_api\Entity\Policymaker|null
   *   Policymaker node or NULL.
   */
  public function getPolicyMaker(?string $id = NULL, ?string $langcode = NULL): ?Policymaker {
    if ($id === NULL) {
      return $this->policymaker;
    }

    $queryResult = $this->query([
      'limit' => 1,
      'policymaker' => $id,
    ]);

    $result = reset($queryResult);
    if (!$result) {
      return NULL;
    }

    if ($langcode === NULL) {
      $langcode = $this->languageManager->getCurrentLanguage()->getId();
    }

    if ($result->hasTranslation($langcode)) {
      $result = $result->getTranslation($langcode);
    }

    assert($result instanceof Policymaker);

    return $result;
  }

  /**
   * Set policy maker by ID.
   *
   * @param string $id
   *   Policy maker ID.
   */
  public function setPolicyMaker(string $id): void {
    $node = $this->getPolicyMaker($id);
    if ($node instanceof NodeInterface) {
      $this->setPolicyMakerNode($node);
    }
  }

  /**
   * Set policy maker node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Policy maker ID.
   */
  public function setPolicyMakerNode(NodeInterface $node): void {
    $currentLanguage = $this->languageManager->getCurrentLanguage()->getId();
    if ($node->hasTranslation($currentLanguage)) {
      $node = $node->getTranslation($currentLanguage);
    }

    if ($node->getType() === 'policymaker') {
      $this->policymaker = $node;
    }

    if ($node->hasField('field_policymaker_id') && !$node->get('field_policymaker_id')->isEmpty()) {
      $this->policymakerId = $node->get('field_policymaker_id')->value;
    }
  }

  /**
   * Set policymaker from current path.
   *
   * @return bool
   *   TRUE if setting pm was successful.
   */
  public function setPolicyMakerByPath(): bool {
    $node = $this->routeMatch->getParameter('node');
    $routeParams = $this->routeMatch->getParameters();
    $currentLanguage = $this->languageManager->getCurrentLanguage()->getId();

    // Determine policymaker in custom routes.
    if (!$node instanceof NodeInterface && $routeParams->has('organization')) {
      $path_prefix = match ($currentLanguage) {
        'sv' => '/beslutsfattare',
        'en' => '/decisionmakers',
        default => '/paattajat',
      };

      // Attempt to load path either by current language path or fallback.
      $organization = $routeParams->get('organization');

      $path = $this->pathAliasManager->getPathByAlias($path_prefix . '/' . $organization, $currentLanguage);
      if (preg_match('/node\/(\d+)/', $path, $matches)) {
        $node = $this->nodeStorage->load($matches[1]);
      }
      elseif ($this->getPolicyMaker($organization) !== NULL) {
        $node = $this->getPolicyMaker($organization);
      }
    }

    // Determine policymaker on subpages.
    if ($node instanceof NodeInterface && $node->getType() !== 'policymaker') {
      $url = Url::fromRoute('<current>')->toString();
      $url_parts = explode('/', trim($url));
      if (count($url_parts) > 4) {
        // Url contains langcode, ie. '/fi/paattajat/kaupunginvaltuusto/*'
        // Alias check fails with langcode - slice only the part we need
        // Ie. '/paattajat/kaupunginvaltuusto'.
        $alias_parts = array_slice($url_parts, 2, 2);
        $path = $this->pathAliasManager->getPathByAlias('/' . implode('/', $alias_parts));
        if (preg_match('/node\/(\d+)/', $path, $matches)) {
          $node = $this->nodeStorage->load($matches[1]);
        }
      }
    }

    if ($node instanceof NodeInterface && $node->bundle() === 'policymaker') {
      $this->setPolicyMakerNode($node);
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Transform org_type value to css class.
   *
   * @param string $org_type
   *   Org type value to transform.
   *
   * @return string
   *   Transformed css class
   */
  public static function transformOrgType(string $org_type): string {
    return Html::cleanCssIdentifier(strtolower($org_type));
  }

  /**
   * Get policymaker route.
   *
   * @param \Drupal\node\NodeInterface|null $policymaker
   *   Policymaker node.
   * @param string|null $langcode
   *   Langcode to get route for.
   *
   * @return \Drupal\Core\Url|null
   *   Policymaker URL.
   */
  public function getPolicymakerRoute(?NodeInterface $policymaker = NULL, ?string $langcode = NULL): ?Url {
    if ($langcode === NULL) {
      $langcode = $this->languageManager->getCurrentLanguage()->getId();
    }
    if ($policymaker === NULL) {
      $policymaker = $this->getPolicyMaker();
    }
    if (!$policymaker instanceof NodeInterface) {
      return NULL;
    }

    $localized_route = 'policymaker.page.' . $langcode;
    if ($this->routeExists($localized_route)) {
      return Url::fromRoute($localized_route, [
        'organization' => $policymaker->getPolicymakerOrganizationFromUrl($langcode),
      ]);
    }
    return NULL;
  }

  /**
   * Return route for policymaker documents.
   *
   * @return Drupal\Core\Url|null
   *   URL object, if route is valid.
   */
  public function getDocumentsRoute(): ?Url {
    if (!$this->policymaker instanceof NodeInterface || $this->policymaker->getType() !== 'policymaker') {
      return NULL;
    }

    if (in_array($this->policymaker->get('field_organization_type')->value, self::TRUSTEE_TYPES)) {
      return NULL;
    }

    $routes = PolicymakerRoutes::getOrganizationRoutes();
    $baseRoute = $routes['documents'];
    $currentLanguage = $this->languageManager->getCurrentLanguage()->getId();
    $localizedRoute = "$baseRoute.$currentLanguage";

    $policymaker_org = $this->policymaker->getPolicymakerOrganizationFromUrl($currentLanguage);

    if ($this->routeExists($localizedRoute)) {
      return Url::fromRoute($localizedRoute, ['organization' => strtolower($policymaker_org)]);
    }

    return NULL;
  }

  /**
   * Return minutes page route by meeting id.
   *
   * @param string $id
   *   Meeting ID.
   * @param string|null $policymaker_id
   *   Policymaker ID. NULL if using default.
   * @param bool $include_anchor
   *   Include decision announcement anchor, if valid.
   * @param string|null $langcode
   *   Language for URL, defaults to current language. Supports fi, en, sv.
   *
   * @return Drupal\Core\Url|null
   *   URL object, if route is valid.
   */
  public function getMinutesRoute(string $id, ?string $policymaker_id = NULL, bool $include_anchor = TRUE, ?string $langcode = NULL): ?Url {
    if (!empty($policymaker_id)) {
      $this->setPolicyMaker($policymaker_id);
    }

    if (!$this->policymaker instanceof NodeInterface) {
      return NULL;
    }

    if (in_array($this->policymaker->get('field_organization_type')->value, self::TRUSTEE_TYPES)) {
      return NULL;
    }

    $policymaker = $this->getPolicymaker();

    if (!$policymaker instanceof NodeInterface || $policymaker->getType() !== 'policymaker') {
      return NULL;
    }

    if (!$langcode) {
      $langcode = $this->languageManager->getCurrentLanguage()->getId();
    }

    $route = PolicymakerRoutes::getSubroutes()['minutes'];
    $localizedRoute = "$route.$langcode";
    $policymaker_org = $policymaker->getPolicymakerOrganizationFromUrl($langcode);

    $routeSettings = [
      'organization' => $policymaker_org,
      'id' => $id,
    ];

    $routeOptions = [
      'language' => $this->languageManager->getLanguage($langcode),
    ];
    if ($include_anchor && $this->checkDecisionAnnouncementById($id)) {
      $anchor = $this->getDecisionAnnouncementAnchor();
      $routeOptions['fragment'] = $anchor;
    }

    if ($this->routeExists($localizedRoute)) {
      return Url::fromRoute($localizedRoute, $routeSettings, $routeOptions);
    }

    return NULL;
  }

  /**
   * Check decision announcement by ID.
   *
   * @param string $id
   *   Meeting ID.
   *
   * @return bool
   *   TRUE if meeting has a decision announcement but no published minutes.
   */
  public function checkDecisionAnnouncementById(string $id): bool {
    $query = $this->nodeStorage
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 1)
      ->condition('type', 'meeting')
      ->condition('field_meeting_id', $id)
      ->condition('field_meeting_decision', '', '<>')
      ->condition('field_meeting_minutes_published', 1, '<>')
      ->range(0, 1);

    $ids = $query->execute();
    return !empty($ids);
  }

  /**
   * Parse decision announcement HTML from meeting node.
   *
   * @param Drupal\node\NodeInterface $meeting
   *   Meeting to get announcement from.
   * @param string $langcode
   *   Langcode used for motion link checking.
   * @param array|null $agendaItems
   *   Agenda items for meetings, used to speed up link fetching.
   *
   * @return array|null
   *   Render array with parsed HTML content, if found.
   */
  public function getDecisionAnnouncement(NodeInterface $meeting, string $langcode, ?array $agendaItems = []): ?array {
    // If meeting minutes are published, do not display announcement.
    if ($meeting->hasField('field_meeting_minutes_published') && $meeting->get('field_meeting_minutes_published')->value) {
      return NULL;
    }

    if (!$meeting->hasField('field_meeting_decision') || $meeting->get('field_meeting_decision')->isEmpty()) {
      return NULL;
    }

    $element_id = $this->getDecisionAnnouncementAnchor();

    $dom = new \DOMDocument();
    @$dom->loadHTML($meeting->get('field_meeting_decision')->value);
    $xpath = new \DOMXPath($dom);
    $announcement_title = NULL;
    $main_title = $xpath->query("//*[contains(@class, 'Paattaja')]");
    if ($main_title) {
      foreach ($main_title as $node) {
        $announcement_title = $this->t('Decision announcement: @title', ['@title' => $node->nodeValue]);
      }
    }

    $announcement_meta = NULL;
    $meta_content = $xpath->query("//*[contains(@class, 'Kokous')]");
    if ($meta_content) {
      foreach ($meta_content as $node) {
        $announcement_meta .= $node->ownerDocument->saveHTML($node);
      }
    }

    $announcement_info = NULL;
    $info_content = $xpath->query("//*[contains(@class, 'Paikka Paivamaara')]");
    if ($info_content->count() > 0) {
      $current_item = $info_content->item(0);
      $announcement_info .= $node->ownerDocument->saveHTML($current_item);
      while ($current_item->nextSibling instanceof \DOMNode) {
        $current_item = $current_item->nextSibling;
        $announcement_info .= $node->ownerDocument->saveHTML($current_item);
      }
    }

    $disclaimer = [];
    if ($this->policymaker instanceof NodeInterface && $this->policymaker->hasField('field_documents_description') && !$this->policymaker->get('field_documents_description')->isEmpty()) {
      $disclaimer['#markup'] = $this->policymaker->get('field_documents_description')->value;
    }

    $main_sections = $xpath->query("//*[@class='Tiedote']");
    if ($main_sections) {
      $accordions = $this->getDecisionAnnouncementSections($main_sections, $langcode, $agendaItems);
    }
    else {
      $accordions = [];
    }

    return [
      'element_id' => $element_id,
      'disclaimer' => $disclaimer,
      'metadata' => ['#markup' => $announcement_meta],
      'heading' => $announcement_title,
      'accordions' => $accordions,
      'more_info' => ['#markup' => $announcement_info],
    ];
  }

  /**
   * Formats decision announcement HTML into accordions.
   *
   * @param \DOMNodeList|null $main_sections
   *   Main sections of the announcement.
   * @param string $langcode
   *   Langcode used for motion link checking.
   * @param array|null $agendaItems
   *   Agenda items for meetings, used to speed up link fetching.
   *
   * @return array
   *   Accordion render array, or empty array.
   */
  public function getDecisionAnnouncementSections(?\DOMNodeList $main_sections, string $langcode, ?array $agendaItems = []): array {
    $accordions = [];

    foreach ($main_sections as $node) {
      $accordion = [];
      $motion_id = NULL;
      foreach ($node->childNodes as $child_node) {
        if (!$child_node instanceof \DOMElement) {
          continue;
        }
        if ($child_node->nodeName === 'h3') {
          $accordion['heading'] = $child_node->nodeValue;
          continue;
        }
        if ($child_node->getAttribute('class') === 'TiedoteTeksti') {
          $accordion['content']['#markup'] = $child_node->ownerDocument->saveHTML($child_node);
        }

        if ($child_node->getAttribute('class') === 'esitysPdfVersioId') {
          $motion_id = trim($child_node->nodeValue);
        }
      }

      // Get link to motion based on native ID.
      $motion_url = NULL;

      // First check agenda because URLs were already generated there.
      foreach ($agendaItems as $item) {
        if ($item['native_id'] === $motion_id) {
          $motion_url = $item['link'];
          break;
        }
      }

      // Next try to get URL without loading nodes.
      if (!empty($motion_id) && !$motion_url instanceof Url) {
        $motion_url = $this->caseService->getDecisionUrlWithoutNode($motion_id, NULL, $langcode);
      }

      // Last try to get URL based on native ID.
      if (!empty($motion_id) && !$motion_url instanceof Url) {
        $motion_url = $this->caseService->getDecisionUrlByNativeId($motion_id, NULL, $langcode);
      }

      if ($motion_url instanceof Url) {
        $accordion['link'] = $motion_url;
      }

      if (!empty($accordion['heading']) && !empty($accordion['content'])) {
        $accordions[] = $accordion;
      }
    }

    return $accordions;
  }

  /**
   * Get decision announcement URL fragment based on active language version.
   *
   * @return string
   *   Element ID or URL fragment.
   */
  public function getDecisionAnnouncementAnchor(?string $langcode = NULL): string {
    if (!$langcode) {
      $langcode = $this->languageManager->getCurrentLanguage()->getId();
    }
    if ($langcode === 'sv') {
      return 'beslutsmeddelanden';
    }
    return 'paatostiedote';
  }

  /**
   * Get localized URL for untranslated policymakers.
   *
   * @param string|null $id
   *   Organization ID.
   * @param string|null $langcode
   *   Langcode for URL.
   *
   * @return \Drupal\Core\Url|null
   *   Localized URL, if route exists.
   */
  public function getLocalizedUrl(?string $id = NULL, ?string $langcode = NULL): ?Url {
    if ($langcode === NULL) {
      $langcode = $this->languageManager->getCurrentLanguage()->getId();
    }

    if ($id === NULL && $this->policymaker->get('langcode')->value === $langcode) {
      return $this->policymaker->toUrl();
    }

    $policymaker = $this->getPolicyMaker($id);
    if (!$policymaker instanceof NodeInterface || !$policymaker->hasField('field_ahjo_title') || $policymaker->get('field_ahjo_title')->isEmpty()) {
      return NULL;
    }

    $policymaker_org = $policymaker->getPolicymakerOrganizationFromUrl($langcode);

    // Use finnish route as default.
    $route = 'policymaker.page.' . $langcode;
    if (!$this->routeExists($route)) {
      $route = 'policymaker.page.fi';
    }

    return Url::fromRoute($route, ['organization' => $policymaker_org]);
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

  /**
   * Get decision list for officials from ElasticSearch Index.
   *
   * @param int|null $limit
   *   Limit results. Defaults to 10000 (maximum amount of results from index).
   * @param bool $byYear
   *   Group decision by year.
   *
   * @return array
   *   List of decisions.
   */
  public function getAgendasListFromElasticSearch(?int $limit = 10000, bool $byYear = TRUE): array {
    if (!$this->policymaker instanceof NodeInterface || !$this->policymakerId) {
      return [];
    }

    if (!$limit) {
      $limit = 10000;
    }

    $langcode = $this->languageManager->getCurrentLanguage()->getId();

    /** @var \Drupal\search_api\IndexInterface $index */
    $index = $this->entityTypeManager->getStorage('search_api_index')->load('decisions');
    $query = $index
      ->query()
      ->range(0, $limit)
      ->addCondition('field_policymaker_id', $this->policymakerId)
      ->addCondition('field_is_decision', TRUE)
      // Sort by date and section number.
      ->sort('meeting_date', 'DESC')
      ->sort('field_decision_section', 'DESC');

    try {
      $results = $query->execute();
    }
    catch (\Throwable $exception) {
      Error::logException($this->logger, $exception);
      return [];
    }

    $data = [];
    foreach ($results as $result) {
      $subject = $result->getField('subject')->getValues()[0];
      $timestamp = $result->getField('meeting_date')->getValues()[0];
      $section = $result->getField('field_decision_section')->getValues()[0];
      $link = $result->getField('decision_url')->getValues()[0];
      $year = date('Y', $timestamp);

      if (!empty($section)) {
        $decision_label = '§ ' . $section . ' ' . $subject;
      }
      else {
        $decision_label = $subject;
      }

      // URL is based on node language, so replace parts of the URL here.
      $search_strings = [
        'fi' => [
          '/fi/',
          'asia',
          'paatos',
        ],
        'sv' => [
          '/sv/',
          'arende',
          'beslut',
        ],
        'en' => [
          '/en/',
          'case',
          'decision',
        ],
      ];

      $localized_link = $link;
      if (str_starts_with($link, '/fi/')) {
        $localized_link = str_replace($search_strings['fi'], $search_strings[$langcode], $link);
      }
      elseif (str_starts_with($link, '/sv/')) {
        $localized_link = str_replace($search_strings['sv'], $search_strings[$langcode], $link);
      }
      elseif (str_starts_with($link, '/en/')) {
        $localized_link = str_replace($search_strings['en'], $search_strings[$langcode], $link);
      }

      $item = [
        'year' => $year,
        'date_desktop' => date('d.m.Y', $timestamp),
        'date_mobile' => date('m - Y', $timestamp),
        'timestamp' => $timestamp,
        'subject' => $decision_label,
        'section' => $section,
        'link' => $localized_link,
      ];

      $data[] = $item;
    }

    $transformedResults = [];
    foreach ($data as $item) {
      if ($byYear) {
        $transformedResults[$item['year']][] = $item;
      }
      else {
        $transformedResults[] = $item;
      }
    }

    return $transformedResults;
  }

  /**
   * Get normalized field_meeting_composition data from policymaker node.
   *
   * @param \Drupal\node\NodeInterface|null $policymaker
   *   Policymaker node.
   *
   * @return array
   *   Meeting composition keyed by alternative spellings of trustee names.
   */
  private function getCompositionDataFromNode(?NodeInterface $policymaker): array {
    if (!$policymaker instanceof NodeInterface || $policymaker->get('field_meeting_composition')->isEmpty()) {
      return [];
    }

    $composition = [];

    foreach ($policymaker->get('field_meeting_composition') as $field) {
      $data = json_decode($field->value, TRUE);
      if (!isset($data['Role']) || !in_array($data['Role'], self::TRUSTEE_ROLES)) {
        continue;
      }
      if (!empty($data)) {
        $formatted_name = $this->formatTrusteeName($data['Name']);

        // Normalize unknown deputyof fields.
        if ($data['DeputyOf'] === 'null null' || $data['DeputyOf'] === 'null') {
          $data['DeputyOf'] = NULL;
        }

        $composition[$formatted_name] = $data;

        // Special handling for combined last names or middle names.
        $alt_formatted_name = $this->formatTrusteeName($data['Name'], TRUE);
        if ($formatted_name !== $alt_formatted_name) {
          $data['orig_name'] = $formatted_name;
          $data['alt_name'] = TRUE;
          $composition[$alt_formatted_name] = $data;
        }
      }
    }

    return $composition;
  }

  /**
   * Get composition data from policymaker node.
   *
   * @param string|null $id
   *   Policymaker ID, leave NULL to use currently set.
   *
   * @return \Drupal\node\NodeInterface[]
   *   Policymaker composition trustee nodes, if found.
   */
  public function getTrustees(?string $id = NULL): array {
    $policymaker = $this->getPolicyMaker($id);
    $composition = $this->getCompositionDataFromNode($policymaker);
    if (empty($composition)) {
      return [];
    }

    $ids = [];
    foreach ($composition as $trustee) {
      if (empty($trustee['ID'])) {
        continue;
      }

      $ids[] = $trustee['ID'];
    }

    return $this->getTrusteeNodesById($ids);
  }

  /**
   * Get policymaker composition based on latest meeting.
   *
   * @param string|null $id
   *   Policymaker ID, leave NULL to use currently set.
   *
   * @return array|null
   *   Policymaker composition, if found.
   */
  public function getComposition(?string $id = NULL): ?array {
    $policymaker = $this->getPolicyMaker($id);
    $composition = $this->getCompositionDataFromNode($policymaker);
    if (empty($composition)) {
      return [];
    }

    $ids = [];
    foreach ($composition as $trustee) {
      if (empty($trustee['ID'])) {
        continue;
      }

      $ids[] = $trustee['ID'];
    }

    $results = [];
    $person_nodes = $this->getTrusteeNodesById($ids);
    $currentLanguage = $this->languageManager->getCurrentLanguage()->getId();
    $imageStyleStorage = $this->entityTypeManager->getStorage('image_style');
    foreach ($person_nodes as $node) {
      if ($node->hasTranslation($currentLanguage)) {
        $node = $node->getTranslation($currentLanguage);
      }

      $name = $node->title->value;

      if ($node->hasField('field_trustee_image') && !$node->get('field_trustee_image')->isEmpty()) {
        $image_uri = $node->get('field_trustee_image')->first()->entity->getFileUri();
        $image_style = $imageStyleStorage->load('policymaker_thumbnail');
        /** @var \Drupal\image\Entity\ImageStyle $image_style */
        $image_url = $image_style?->buildUrl($image_uri);
      }
      else {
        $image_url = NULL;
      }

      if (isset($composition[$name])) {
        $url = $this->getTrusteeUrl($node);
        if ($url instanceof Url) {
          $url = $url->toString();
        }
        else {
          $url = NULL;
        }

        $role = $this->getTranslationForRole($composition[$name]['Role']);

        $results[] = [
          'first_name' => $node->get('field_first_name')->value,
          'last_name' => $node->get('field_last_name')->value,
          'image_url' => $image_url,
          'url' => $url,
          'role' => $role,
          'role_orig' => $composition[$name]['Role'],
          'email' => $node->get('field_trustee_email')->value,
          'party' => $node->get('field_trustee_council_group')->value,
          'deputy_of' => $composition[$name]['DeputyOf'],
        ];

        // Remove from composition so we have a list of non council trustees.
        if (isset($composition[$name]['alt_name']) && $composition[$name]['alt_name']) {
          $orig_name = $composition[$name]['orig_name'];
          unset($composition[$orig_name]);
        }
        unset($composition[$name]);
      }
    }

    // Add remaining people to results.
    foreach ($composition as $data) {
      if (isset($data['alt_name']) && $data['alt_name']) {
        continue;
      }
      $bits = explode(' ', $data['Name']);
      $last_name = array_pop($bits);
      $first_name = implode(' ', $bits);
      $role = $this->getTranslationForRole($data['Role']);
      $results[] = [
        'first_name' => $first_name,
        'last_name' => $last_name,
        'image_url' => NULL,
        'url' => NULL,
        'role' => $role,
        'role_orig' => $data['Role'],
        'email' => NULL,
        'party' => NULL,
        'deputy_of' => $data['DeputyOf'],
      ];
    }
    return $results;
  }

  /**
   * Get translation for role string.
   *
   * @param string $role
   *   Role to translate.
   *
   * @return string
   *   Translated string or original.
   */
  public function getTranslationForRole(string $role): string {
    $context = ['context' => 'Council group members list'];
    switch ($role) {
      case 'Jäsen':
        $translation = $this->t('Member', [], $context);
        break;

      case 'Varajäsen':
        $translation = $this->t('Deputy member', [], $context);
        break;

      case 'Puheenjohtaja':
        $translation = $this->t('Chairman', [], $context);
        break;

      case 'Varapuheenjohtaja':
        $translation = $this->t('Vice chairman', [], $context);
        break;

      default:
        $translation = NULL;
        break;
    }

    if ($translation) {
      return (string) $translation;
    }

    return $role;
  }

  /**
   * Get localized trustee URL.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Trustee node.
   * @param string|null $langcode
   *   Langcode to get URL for.
   *
   * @return \Drupal\Core\Url|null
   *   Localized trustee URL, if found.
   */
  public function getTrusteeUrl(NodeInterface $node, ?string $langcode = NULL): ?Url {
    if ($langcode === NULL) {
      $langcode = $this->languageManager->getCurrentLanguage()->getId();
    }

    if ($node->get('langcode')->value === $langcode) {
      return $node->toUrl()->setAbsolute(TRUE);
    }

    if (!$node->hasField('field_trustee_id') || $node->get('field_trustee_id')->isEmpty()) {
      return NULL;
    }

    $trustee_id = $this->pathAliasCleaner->cleanString($node->get('field_trustee_id')->value);

    $localized_route = 'policymaker.page.' . $langcode;
    if ($this->routeExists($localized_route)) {
      return Url::fromRoute($localized_route, [
        'organization' => $trustee_id,
      ])->setAbsolute(TRUE);
    }
    return NULL;

  }

  /**
   * Get trustee node by path alias segment.
   *
   * @param string $agent_id
   *   Cleaned up version of agent id, without special characters.
   *
   * @return \Drupal\node\NodeInterface|null
   *   Trustee node, if found.
   */
  public function getTrusteeByPath(string $agent_id): ?NodeInterface {
    $currentLanguage = $this->languageManager->getCurrentLanguage()->getId();
    $fallback_prefix = '/paattajat';
    $fallback_lang = 'fi';
    if ($currentLanguage === 'sv') {
      $path_prefix = '/beslutsfattare';
    }
    else {
      $path_prefix = '/paattajat';
    }

    $path = $this->pathAliasManager->getPathByAlias($path_prefix . '/' . $agent_id, $currentLanguage);
    $fallback_path = $this->pathAliasManager->getPathByAlias($fallback_prefix . '/' . $agent_id, $fallback_lang);

    $node = NULL;
    if (preg_match('/node\/(\d+)/', $path, $matches)) {
      $node = $this->nodeStorage->load($matches[1]);
    }
    elseif (preg_match('/node\/(\d+)/', $fallback_path, $matches)) {
      $node = $this->nodeStorage->load($matches[1]);
    }

    return $node;
  }

  /**
   * Load trustee nodes based on IDs.
   *
   * @param array $ids
   *   Agent IDs of trustee nodes to load.
   *
   * @return array|null
   *   Array of nodes.
   */
  private function getTrusteeNodesById(array $ids): ?array {
    $query = $this->nodeStorage
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 1)
      ->condition('type', 'trustee')
      ->condition('field_trustee_id', $ids, 'IN');

    $ids = $query->execute();

    if (empty($ids)) {
      return [];
    }

    return $this->nodeStorage->loadMultiple($ids);
  }

  /**
   * Format trustee name to match data from API.
   *
   * @param string $name
   *   Name to format.
   * @param bool $alt_format
   *   Use alternative formatting (multiple last names).
   *
   * @return string
   *   Name formatted to: Lastname, Firstname
   */
  private function formatTrusteeName(string $name, $alt_format = FALSE): string {
    $bits = explode(' ', $name);
    if ($alt_format && count($bits) > 2) {
      $last_names = implode(' ', array_slice($bits, -2));
      $first_names = implode(' ', array_slice($bits, 0, -2));
    }
    else {
      $last_names = array_pop($bits);
      $first_names = implode(' ', $bits);
    }

    return $last_names . ', ' . $first_names;
  }

  /**
   * Get API-retrieved minutes and agendas from ElasticSearch Index.
   *
   * @param int|null $limit
   *   Limit results. Defaults to 10000 (maximum amount of results from index).
   * @param bool $byYear
   *   Group decision by year.
   *
   * @return array
   *   List of meeting documents.
   */
  public function getApiMinutesFromElasticSearch(?int $limit = NULL, bool $byYear = FALSE): array {
    if (!$this->policymaker instanceof NodeInterface || !$this->policymakerId) {
      return [];
    }

    if (!$limit) {
      $limit = 10000;
    }

    /** @var \Drupal\search_api\IndexInterface $index */
    $index = $this->entityTypeManager->getStorage('search_api_index')->load('meetings');

    $query = $index
      ->query()
      ->range(0, $limit)
      ->addCondition('field_meeting_dm_id', $this->policymakerId)
      ->addCondition('field_meeting_date', self::MEETING_START_DATE, '>=')
      ->addCondition('field_meeting_status', 'peruttu', '<>')
      ->addCondition('meeting_phase', '', '<>')
      ->sort('field_meeting_date', 'DESC');

    try {
      $results = $query->execute();
    }
    catch (\Throwable $exception) {
      Error::logException($this->logger, $exception);
      return [];
    }

    $data = [];
    foreach ($results as $result) {
      $meeting_timestamp = $result->getField('field_meeting_date')->getValues()[0];
      $meeting_year = date('Y', $meeting_timestamp);
      $meeting_id = $result->getField('field_meeting_id')->getValues()[0];
      $phase = $result->getField('meeting_phase')->getValues()[0];
      $document_title = NULL;
      $decision_link = NULL;
      $link = $this->getMinutesRoute($meeting_id, NULL, FALSE);

      if ($phase === 'minutes') {
        $document_title = $this->t('Minutes');
      }
      elseif ($phase === 'agenda' || $phase === 'decision') {
        $document_title = $this->t('Agenda');
      }

      if ($phase === 'decision') {
        $decision_link = $this->getMinutesRoute($meeting_id);
      }

      $item = [
        'id' => $result->getField('field_meeting_id')->getValues()[0],
        'publish_date' => date('d.m.Y', $meeting_timestamp),
        'publish_date_short' => date('m - Y', $meeting_timestamp),
        'title' => $document_title,
        'phase' => $phase,
        'meeting_number' => $result->getField('field_meeting_sequence_number')->getValues()[0] . ' - ' . $meeting_year,
        'link' => $link,
        'decision_link' => $decision_link,
      ];

      if ($byYear) {
        $data[$meeting_year][] = $item;
      }
      else {
        $data[] = $item;
      }
    }

    return $data;
  }

  /**
   * Get meeting node for this policymaker.
   *
   * @param string $meetingId
   *   Meeting ID to load.
   *
   * @return \Drupal\node\NodeInterface|null
   *   Meeting node or NULL if one can't be loaded.
   */
  public function getMeetingNode(string $meetingId): ?NodeInterface {
    if (!$this->policymakerId) {
      return NULL;
    }

    $query = $this->nodeStorage
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 1)
      ->condition('type', 'meeting')
      ->condition('field_meeting_dm_id', $this->policymakerId)
      ->range(0, 1)
      ->sort('field_meeting_date', 'DESC');

    if ($meetingId) {
      $query->condition('field_meeting_id', $meetingId);
    }

    $ids = $query->execute();

    if (empty($ids)) {
      return NULL;
    }

    $id = reset($ids);
    return $this->nodeStorage->load($id);
  }

  /**
   * Get meeting title from node.
   *
   * @param \Drupal\node\NodeInterface $meeting
   *   Meeting node.
   *
   * @return string
   *   Formatted meeting title.
   */
  public function getMeetingTitle(NodeInterface $meeting): string {
    $policymaker_title = $this->policymaker->get('field_ahjo_title')->value;
    $meeting_timestamp = strtotime($meeting->get('field_meeting_date')->value);
    $meetingNumber = $meeting->get('field_meeting_sequence_number')->value;
    $meetingYear = date('Y', $meeting_timestamp);
    return $policymaker_title . ' ' . $meetingNumber . '/' . $meetingYear;
  }

  /**
   * Return all agenda items for a meeting. Only works on policymaker subpages.
   *
   * @param string $meetingId
   *   Meeting ID to get Agenda Items for.
   *
   * @return array|null
   *   Agenda items for meeting.
   */
  public function getMeetingAgenda(string $meetingId): ?array {
    if (!$this->policymaker instanceof NodeInterface || !$this->policymakerId) {
      throw new \InvalidArgumentException("Missing policymaker");
    }

    $meeting = $this->getMeetingNode($meetingId);
    if (!$meeting instanceof NodeInterface) {
      throw new NotFoundHttpException();
    }

    $publishDate = NULL;
    $fileUrl = NULL;

    // Use either current language or fallback language for agenda items.
    $currentLanguage = $this->languageManager->getCurrentLanguage()->getId();

    // Prefer pöytäkirja if it exists.
    if ($document = $this->getMeetingDocumentWithLanguageFallback($meeting, 'pöytäkirja', $currentLanguage)) {
      $pageTitle = $this->t('Minutes');
      $documentTitle = $this->t('Minutes publication date');
    }
    // Fall back to esityslist.
    elseif ($document = $this->getMeetingDocumentWithLanguageFallback($meeting, 'esityslista', $currentLanguage)) {
      $pageTitle = $this->t('Agenda');
      $documentTitle = $this->t('Agenda publication date');
    }
    else {
      $pageTitle = $this->t('Meeting');
      $documentTitle = NULL;
    }

    if (!empty($document)) {
      if (!empty($document['Issued'])) {
        $document_timestamp = strtotime($document['Issued']);
        $publishDate = date('d.m.Y', $document_timestamp);
      }

      $fileUrl = $this->meetingService->getUrlFromAhjoDocument($document);
    }

    // Get items in both languages because all aren't translated.
    $agendaItems = $this->getAgendaItems($meeting->get('field_meeting_agenda'), $meetingId, $currentLanguage);

    $fallbackLanguage = match ($currentLanguage) {
      'fi' => 'sv',
      default => 'fi',
    };
    $fallbackAgendaItems = $this->getAgendaItems($meeting->get('field_meeting_agenda'), $meetingId, $fallbackLanguage);
    // If the default language list is missing items, add them from fallback.
    if (count($agendaItems) < count($fallbackAgendaItems)) {
      // Initiate new list to keep correct ordering.
      $newList = [];
      foreach ($fallbackAgendaItems as $key => $item) {
        if (isset($agendaItems[$key])) {
          $newList[$key] = $agendaItems[$key];
        }
        else {
          $newList[$key] = $item;
        }
      }
      $agendaItems = $newList;
    }

    // Meeting metadata.
    $metadata = [];
    if ($meeting->hasField('field_meeting_date') && !$meeting->get('field_meeting_date')->isEmpty()) {
      $metadata['date'] = date('d.m.Y - H:i', $meeting->get('field_meeting_date')->date->getTimeStamp());
    }
    if ($meeting->hasField('field_meeting_location') && !$meeting->get('field_meeting_location')->isEmpty()) {
      $metadata['location'] = $meeting->get('field_meeting_location')->value;
    }

    // Decision announcement.
    $decisionAnnouncement = $this->getDecisionAnnouncement($meeting, $currentLanguage, $agendaItems);

    $meeting_timestamp = strtotime($meeting->get('field_meeting_date')->value);

    return [
      'meeting' => [
        'nid' => $meeting->id(),
        'page_title' => $pageTitle,
        'date_long' => date('d.m.Y', $meeting_timestamp),
        'title' => $this->getMeetingTitle($meeting),
      ],
      'meeting_metadata' => $metadata,
      'decision_announcement' => $decisionAnnouncement,
      'list' => $agendaItems,
      'file' => [
        'document_title' => $documentTitle,
        'file_url' => $fileUrl,
        'publish_date' => $publishDate,
      ],
    ];
  }

  /**
   * Find translated meeting document with language fallback.
   *
   * Algorithm:
   * - Try to find the document in the current language.
   * - Fallback to 'fi-sv' langcode.
   * - Fallback to 'fi', if we didn't try it already, otherwise try 'sv'.
   *
   * @return null|array
   *   Null if no document is found.
   */
  private function getMeetingDocumentWithLanguageFallback(NodeInterface $meeting, string $type, string $currentLanguage): ?array {
    $documentLanguages = [$currentLanguage, 'fi-sv'];
    $documentLanguages[] = match ($currentLanguage) {
      'fi' => 'sv',
      default => 'fi',
    };

    foreach ($documentLanguages as $documentLanguage) {
      $document = $this->meetingService->getDocumentFromEntity($meeting, $type, $documentLanguage);

      if (!is_null($document)) {
        return $document;
      }
    }

    return NULL;
  }

  /**
   * Get agenda items.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $list_field
   *   Field to get agenda items from.
   * @param string $meeting_id
   *   Meeting ID.
   * @param string $langcode
   *   Langcode to use.
   *
   * @return array
   *   Array of agenda items. Can be empty.
   */
  private function getAgendaItems(FieldItemListInterface $list_field, string $meeting_id, string $langcode = 'fi'): array {
    $agendaItems = [];
    foreach ($list_field as $item) {

      $data = json_decode($item->value, TRUE);

      if (!$data) {
        continue;
      }

      if ($data['PDF']['Language'] !== $langcode) {
        continue;
      }

      if (!empty($data['Section']) && !empty($data['AgendaPoint']) && $data['AgendaPoint'] !== 'null') {
        $index = $this->t('Case @point. / @section', [
          '@point' => $data['AgendaPoint'],
          '@section' => $data['Section'],
        ]);
      }
      elseif (!empty($data['Section'])) {
        $index = $data['Section'];
      }
      elseif (!empty($data['AgendaPoint']) && $data['AgendaPoint'] !== 'null') {
        $index = $data['AgendaPoint'] . '.';
      }
      else {
        $index = '';
      }

      // First, try getting decision URL without loading nodes.
      // This is based on diary number and native ID.
      $agenda_link = NULL;
      $native_id = NULL;
      if (!empty($data['PDF']) && !empty($data['PDF']['NativeId'])) {
        $native_id = $data['PDF']['NativeId'];
        $agenda_link = $this->caseService->getDecisionUrlWithoutNode($native_id, $data['CaseIDLabel'], $langcode);
      }

      // Next, try with native ID.
      if (!$agenda_link && !empty($data['PDF']) && !empty($data['PDF']['NativeId'])) {
        $agenda_link = $this->caseService->getDecisionUrlByNativeId($data['PDF']['NativeId']);
      }

      // Next, try with version series ID.
      if (!$agenda_link && !empty($data['PDF']) && !empty($data['PDF']['VersionSeriesId'])) {
        $agenda_link = $this->caseService->getDecisionUrlByVersionSeriesId($data['PDF']['VersionSeriesId']);
      }

      // If a decision can't be found with ID or series ID, try with title.
      if (!$agenda_link && !empty($data['Section']) && !empty($data['AgendaItem'])) {
        $section_clean = (string) intval($data['Section']);
        $agenda_link = $this->caseService->getDecisionUrlByTitle($data['AgendaItem'], $meeting_id, $section_clean);
      }
      elseif (!$agenda_link) {
        $agenda_link = $this->caseService->getDecisionUrlByTitle($data['AgendaItem'], $meeting_id);
      }

      // Prevent PHP warnings if sequence or section number is missing.
      if (empty($data['AgendaPoint'])) {
        $data['AgendaPoint'] = '';
      }
      if (empty($data['Section'])) {
        $data['Section'] = '';
      }

      $agendaItems[] = [
        'subject' => $data['AgendaItem'],
        'sequence' => (int) $data['AgendaPoint'],
        'section' => (int) $data['Section'],
        'index' => $index,
        'link' => $agenda_link,
        'native_id' => $native_id,
      ];
    }

    // Sort agenda items first by sequence number, then by section number.
    // Missing section or sequence numbers will be handled as 0.
    usort($agendaItems, function ($item1, $item2) {
      return $item1['sequence'] - $item2['sequence'];
    });
    usort($agendaItems, function ($item1, $item2) {
      return $item1['section'] - $item2['section'];
    });

    return $agendaItems;
  }

  /**
   * Get discussion minutes for meeting.
   *
   * @param int|null $limit
   *   Limit query.
   * @param bool $byYear
   *   Search by year.
   * @param string $meetingId
   *   Filter by meeting.
   *
   * @return array
   *   Meeting document data.
   */
  public function getMinutesOfDiscussion(?int $limit = NULL, bool $byYear = FALSE, ?string $meetingId = NULL) : array {
    if (!$this->policymaker instanceof NodeInterface || !$this->policymakerId) {
      return [];
    }

    $query = $this->nodeStorage
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 1)
      ->condition('type', 'meeting')
      ->condition('field_meeting_dm_id', $this->policymakerId)
      ->sort('field_meeting_date', 'DESC');

    if ($limit) {
      $query->range('0', $limit);
    }

    if ($meetingId) {
      $query->condition('field_meeting_id', $meetingId);
    }

    $ids = $query->execute();

    if (empty($ids)) {
      return [];
    }

    $meeting_minutes = $this->getMeetingMediaEntities($ids);
    $meeting_ids = array_keys($meeting_minutes);
    $nodes = $this->nodeStorage->loadMultiple($meeting_ids);
    $fileStorage = $this->entityTypeManager->getStorage('file');
    /** @var \Drupal\Core\Entity\FieldableEntityInterface[] $nodes */
    $transformedResults = [];
    foreach ($meeting_minutes as $meeting_id => $meeting) {
      foreach ($meeting as $entity) {
        $meeting_timestamp = $nodes[$meeting_id]->get('field_meeting_date')->date->getTimeStamp();
        $meeting_year = date('Y', $meeting_timestamp);
        $dateLong = date('d.m.Y', $meeting_timestamp);

        $result = [
          'publish_date' => $dateLong,
          'title' => $entity->label() . ' (PDF)',
          'type' => 'minutes-of-discussion',
          'year' => $meeting_year,
        ];

        $download_link = NULL;
        $file = NULL;
        if ($entity->get('field_document')->target_id) {
          $file_id = $entity->get('field_document')->target_id;
          $file = $fileStorage->load($file_id);
        }

        if ($file instanceof FileInterface) {
          $download_link = $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
        }

        if (!$download_link) {
          continue;
        }

        $result['link'] = $download_link;

        $transformedResults[] = $result;
      }
    }

    usort($transformedResults, function ($item1, $item2) {
      return strtotime($item2['publish_date']) - strtotime($item1['publish_date']);
    });

    if ($byYear) {
      $sorted_by_year = [];
      foreach ($transformedResults as $result) {
        $sorted_by_year[$result['year']][] = $result;
      }

      $transformedResults = $sorted_by_year;
    }

    return $transformedResults;
  }

  /**
   * Get meeting-related documents.
   *
   * @return array
   *   Array of resulting documents
   */
  private function getMeetingMediaEntities($meetingids) {
    if (count($meetingids) === 0) {
      return [];
    }

    $mediaStorage = $this->entityTypeManager->getStorage('media');
    $ids = $mediaStorage->getQuery()
      ->accessCheck(TRUE)
      ->condition('bundle', 'minutes_of_the_discussion')
      ->condition('field_meetings_reference', $meetingids, 'IN')
      ->execute();

    $entities = $mediaStorage->loadMultiple($ids);
    /** @var \Drupal\Core\Entity\FieldableEntityInterface[] $entities */
    $result = [];
    foreach ($entities as $entity) {
      $meeting_id = $entity->get('field_meetings_reference')->target_id;
      $result[$meeting_id][] = $entity;
    }

    return $result;
  }

  /**
   * Gets all initiatives from trustee nodes.
   *
   * @return array
   *   List of initiatives.
   */
  public function getAllInitiatives(): array {
    $nids = $this->nodeStorage
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'trustee')
      ->condition('status', 1)
      ->condition('field_trustee_initiatives', '', '<>')
      ->execute();

    $nodes = $this->nodeStorage->loadMultiple($nids);
    $initiatives = [];
    foreach ($nodes as $node) {
      if (!$node instanceof NodeInterface || !$node->hasField('field_trustee_initiatives')) {
        continue;
      }

      $id = $node->get('field_trustee_id')->value;

      foreach ($node->get('field_trustee_initiatives') as $field) {
        $initiative = json_decode($field->value, TRUE);
        $initiative['TrusteeID'] = $id;
        $initiatives[] = $initiative;
      }
    }

    return $initiatives;
  }

  /**
   * Get organization display type from node. Shouldn't be used for type checks.
   *
   * @param Drupal\node\NodeInterface|null $node
   *   Policymaker node. Leave empty to use set policymaker.
   *
   * @return string|null
   *   Overridden, default or NULL if node can't be found.
   */
  public function getPolicymakerTypeFromNode(?NodeInterface $node = NULL): ?string {
    $currentLanguage = $this->languageManager->getCurrentLanguage()->getId();

    if ($node === NULL) {
      $node = $this->getPolicyMaker();
    }
    if (!$node instanceof NodeInterface) {
      return NULL;
    }

    // First check if label has been manually overriden.
    if ($node->hasField('field_custom_organization_type') && !$node->get('field_custom_organization_type')->isEmpty()) {
      return $node->get('field_custom_organization_type')->value;
    }

    // Check if node is a city council division.
    if ($node->hasField('field_city_council_division') && $node->get('field_city_council_division')->value) {
      return $this->getPolicymakerType('Kaupunginhallituksen jaosto', $currentLanguage);
    }

    // Return NULL if organization type field is empty and no override is set.
    if (!$node->hasField('field_organization_type') || $node->get('field_organization_type')->isEmpty()) {
      return NULL;
    }

    // Check org type field.
    return $this->getPolicymakerType($node->get('field_organization_type')->value, $currentLanguage);
  }

  /**
   * Get display version of organization type.
   *
   * @param string $type
   *   Org type to check.
   * @param string $langcode
   *   Langcode to get org type for.
   *
   * @return string
   *   Translated version of type stored in config.
   */
  public function getPolicymakerType(string $type, string $langcode = 'fi'): string {
    $config = $this->config->get('paatokset_ahjo_api.policymaker_labels');
    $key = Html::cleanCssIdentifier(strtolower($type)) . '_' . $langcode;
    if ($value = $config->get($key)) {
      return $value;
    }
    return $type;
  }

  /**
   * Get organization display type by ID.
   *
   * @param string $id
   *   Policymaker ID.
   *
   * @return string|null
   *   Orginzation display type or NULL if policymaker can't be found.
   */
  public function getPolicymakerTypeById(string $id): ?string {
    $node = $this->getPolicyMaker($id);
    if ($node instanceof NodeInterface) {
      return $this->getPolicymakerTypeFromNode($node);
    }
    return NULL;
  }

  /**
   * Get organization tag render array.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Policymaker node.
   *
   * @return array|null
   *   Render array for tag.
   */
  public function getPolicyMakerTag(NodeInterface $node): ?array {
    $type = $this->getPolicymakerTypeFromNode($node);
    assert($node instanceof Policymaker);
    $color = $node->getPolicymakerClass();

    if (!$type) {
      return NULL;
    }

    return [
      '#prefix' => '<div class="policymaker-summary__label"><div role="region" class="issue-content__policymaker-label policymaker-label--small ' . $color . '">',
      '#suffix' => '</div></div>',
      '#markup' => $type,
    ];
  }

  /**
   * Get language versions of finnish sector name.
   *
   * @param string $sector
   *   Sector name.
   * @param string $language
   *   Language to get translation for.
   *
   * @return string
   *   English translation of sector, or original value.
   */
  public function getSectorTranslation(string $sector, string $language = 'en'): string {
    switch ($sector) {
      case 'Kasvatuksen ja koulutuksen toimiala':
        $values = [
          'en' => 'Education Division',
          'fi' => 'Kasvatuksen ja koulutuksen toimiala',
          'sv' => 'Sektorn för fostran och utbildning',
        ];
        break;

      case 'Kaupunkiympäristön toimiala':
        $values = [
          'en' => 'Urban Environment Division',
          'fi' => 'Kaupunkiympäristön toimiala',
          'sv' => 'Stadsmiljösektorn',
        ];
        break;

      case 'Keskushallinto':
        $values = [
          'en' => 'Central Administration',
          'fi' => 'Keskushallinto',
          'sv' => 'Centralförvaltningen',
        ];
        break;

      case 'Kulttuurin ja vapaa-ajan toimiala':
        $values = [
          'en' => 'Culture and Leisure Division',
          'fi' => 'Kulttuurin ja vapaa-ajan toimiala',
          'sv' => 'Kultur- och fritidssektorn',
        ];
        break;

      case 'Sosiaali- ja terveystoimiala':
        $values = [
          'en' => 'Social Services and Health Care Division',
          'fi' => 'Sosiaali- ja terveystoimiala',
          'sv' => 'Social- och hälsovårdsektorn',
        ];
        break;

      case 'Sosiaali-, terveys- ja pelastustoimiala':
        $values = [
          'en' => 'Social Services, Health Care and Rescue Services Division',
          'fi' => 'Sosiaali-, terveys- ja pelastustoimiala',
          'sv' => 'Social-, hälsovårds- och räddningssektorn',
        ];
        break;

      default:
        $values = [];
        break;
    }

    if (!empty($values) && isset($values[$language])) {
      return $values[$language];
    }
    else {
      return $sector;
    }
  }

}
