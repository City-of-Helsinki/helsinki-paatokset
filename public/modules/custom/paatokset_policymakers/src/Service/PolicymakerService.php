<?php

namespace Drupal\paatokset_policymakers\Service;

use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;
use Drupal\paatokset_policymakers\Enum\PolicymakerRoutes;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Service class for retrieving policymaker-related data.
 *
 * @package Drupal\paatokset_ahjo_api\Serivces
 */
class PolicymakerService {
  /**
   * Machine name for meeting node type.
   */
  const NODE_TYPE = 'policymaker';

  /**
   * Policymaker node.
   *
   * @var \Drupal\node\Entity\Node
   */
  private $policymaker;

  /**
   * Policymaker ID.
   *
   * @var string
   */
  private $policymakerId;

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
    if (isset($params['sort'])) {
      $sort = $params['sort'];
    }
    else {
      $sort = 'ASC';
    }

    $query = \Drupal::entityQuery('node')
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

    return Node::loadMultiple($ids);
  }

  /**
   * Get policymaker node by ID.
   *
   * @param string|null $id
   *   Policymaker id. Leave null to return current instance.
   *
   * @return Drupal\node\NodeInterface|null
   *   Policymaker node or NULL.
   */
  public function getPolicyMaker(?string $id = NULL): ?Node {
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
   * @param Drupal\node\NodeInterface $node
   *   Policy maker ID.
   */
  public function setPolicyMakerNode(NodeInterface $node): void {
    if ($node->getType() === 'policymaker') {
      $this->policymaker = $node;
    }

    if ($node->hasField('field_policymaker_id') && !$node->get('field_policymaker_id')->isEmpty()) {
      $this->policymakerId = $node->get('field_policymaker_id')->value;
    }
  }

  /**
   * Set policy maker by current path.
   */
  public function setPolicyMakerByPath(): void {
    $node = \Drupal::routeMatch()->getParameter('node');
    $routeParams = \Drupal::routeMatch()->getParameters();

    // Determine policymaker in custom routes.
    if (!$node instanceof NodeInterface && $routeParams->get('organization')) {
      // Default path and language for policymakers should always be finnish.
      $path_prefix = '/paattajat';
      $path_lang = 'fi';
      $organization = $routeParams->get('organization');
      $path = \Drupal::service('path_alias.manager')->getPathByAlias($path_prefix . '/' . $organization, $path_lang);
      if (preg_match('/node\/(\d+)/', $path, $matches)) {
        $node = Node::load($matches[1]);
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
        $path = \Drupal::service('path_alias.manager')->getPathByAlias('/' . implode('/', $alias_parts));
        if (preg_match('/node\/(\d+)/', $path, $matches)) {
          $node = Node::load($matches[1]);
        }
      }
    }

    if ($node instanceof NodeInterface) {
      $this->setPolicyMakerNode($node);
    }
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
   * Return route for policymaker documents.
   *
   * @return Drupal\Core\Url|null
   *   URL object, if route is valid.
   */
  public function getDocumentsRoute(): ?Url {
    if (!$this->policymaker instanceof NodeInterface || $this->policymaker->getType() !== 'policymaker') {
      return NULL;
    }

    if ($this->policymaker->get('field_organization_type')->value === 'Luottamushenkilö') {
      return NULL;
    }

    $routes = PolicymakerRoutes::getOrganizationRoutes();
    $baseRoute = $routes['documents'];
    $currentLanguage = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $localizedRoute = "$baseRoute.$currentLanguage";

    $policymaker_url = $this->policymaker->toUrl()->toString();
    $policymaker_url_bits = explode('/', $policymaker_url);
    $policymaker_org = array_pop($policymaker_url_bits);

    if ($this->routeExists($localizedRoute)) {
      return Url::fromRoute($localizedRoute, ['organization' => strtolower($policymaker_org)]);
    }

    return NULL;
  }

  /**
   * Return route for policymaker decisions.
   *
   * @param string|null $policymaker_id
   *   Policymaker ID or NULL to use selected one.
   *
   * @return Drupal\Core\Url|null
   *   URL object, if route is valid.
   */
  public function getDecisionsRoute(?string $policymaker_id = NULL): ?Url {
    $trustee_types = [
      'Viranhaltija',
      'Luottamushenkilö',
    ];

    if (!empty($policymaker_id)) {
      $this->setPolicyMaker($policymaker_id);
    }

    if (!$this->policymaker instanceof NodeInterface || !$this->policymaker->hasField('field_organization_type')) {
      return NULL;
    }

    if (!in_array($this->policymaker->get('field_organization_type')->value, $trustee_types)) {
      return NULL;
    }

    $policymaker_url = $this->policymaker->toUrl()->toString();
    $policymaker_url_bits = explode('/', $policymaker_url);
    $policymaker_org = array_pop($policymaker_url_bits);

    $routes = PolicymakerRoutes::getTrusteeRoutes();
    $baseRoute = $routes['decisions'];
    $currentLanguage = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $localizedRoute = "$baseRoute.$currentLanguage";

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
   *
   * @return Drupal\Core\Url|null
   *   URL object, if route is valid.
   */
  public function getMinutesRoute(string $id, ?string $policymaker_id = NULL, bool $include_anchor = TRUE): ?Url {
    if (!empty($policymaker_id)) {
      $this->setPolicyMaker($policymaker_id);
    }

    if (!$this->policymaker instanceof NodeInterface) {
      return NULL;
    }

    if ($this->policymaker->get('field_organization_type')->value === 'Luottamushenkilö') {
      return NULL;
    }

    $policymaker = $this->getPolicymaker();

    if (!$policymaker instanceof NodeInterface || $policymaker->getType() !== 'policymaker') {
      return NULL;
    }

    $policymaker_url = $policymaker->toUrl()->toString();
    $policymaker_url_bits = explode('/', $policymaker_url);
    $policymaker_org = array_pop($policymaker_url_bits);

    $route = PolicymakerRoutes::getSubroutes()['minutes'];
    $currentLanguage = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $localizedRoute = "$route.$currentLanguage";

    $routeSettings = [
      'organization' => strtolower($policymaker_org),
      'id' => $id,
    ];

    $routeOptions = [];
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
    $query = \Drupal::entityQuery('node')
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
   *
   * @return array|null
   *   Render array with parsed HTML content, if found.
   */
  public function getDecisionAnnouncement(NodeInterface $meeting): ?array {
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
        $announcement_title = t('Decision announcement: @title', ['@title' => $node->nodeValue]);
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

    $accordions = [];
    $main_sections = $xpath->query("//*[@class='Tiedote']");
    if ($main_sections) {
      foreach ($main_sections as $node) {
        $accordion = [];
        foreach ($node->childNodes as $child_node) {
          if ($child_node->nodeName === 'h3') {
            $accordion['heading'] = $child_node->nodeValue;
            continue;
          }
          if ($child_node->getAttribute('class') === 'TiedoteTeksti') {
            $accordion['content']['#markup'] = $child_node->ownerDocument->saveHTML($child_node);
          }
        }

        if (!empty($accordion['heading']) && !empty($accordion['content'])) {
          $accordions[] = $accordion;
        }
      }
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
   * Get decision announcement URL fragment based on active language version.
   *
   * @return string
   *   Element ID or URL fragment.
   */
  public function getDecisionAnnouncementAnchor(): string {
    $currentLanguage = \Drupal::languageManager()->getCurrentLanguage()->getId();
    if ($currentLanguage === 'sv') {
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
      $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
    }

    if ($id === NULL && $this->policymaker->get('langcode')->value === $langcode) {
      return $this->policymaker->toUrl();
    }

    $policymaker = $this->getPolicyMaker($id);
    if (!$policymaker instanceof NodeInterface || !$policymaker->hasField('field_ahjo_title') || $policymaker->get('field_ahjo_title')->isEmpty()) {
      return NULL;
    }

    $organization = strtolower($policymaker->get('field_ahjo_title')->value);

    // Use finnish route as default.
    $route = 'policymaker.page.' . $langcode;
    if (!$this->routeExists($route)) {
      $route = 'policymaker.page.fi';
    }

    return Url::fromRoute($route, ['organization' => $organization]);
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
   * Get decision list for officials.
   *
   * @param int|null $limit
   *   Limit results.
   * @param bool $byYear
   *   Group decision by year.
   *
   * @return array
   *   List of decisions.
   */
  public function getAgendasList(?int $limit = 0, bool $byYear = TRUE): array {
    if (!$this->policymaker instanceof NodeInterface || !$this->policymakerId) {
      return [];
    }

    $query = \Drupal::entityQuery('node')
      ->condition('status', 1)
      ->condition('type', 'decision')
      ->condition('field_policymaker_id', $this->policymakerId)
      ->condition('field_meeting_date', '', '<>')
      ->sort('field_meeting_date', 'DESC');

    if ($limit) {
      $query->range('0', $limit);
    }

    $ids = $query->execute();

    if (empty($ids)) {
      return [];
    }

    $nodes = Node::loadMultiple($ids);

    $transformedResults = [];
    foreach ($nodes as $node) {
      $timestamp = strtotime($node->get('field_meeting_date')->value);
      $year = date('Y', $timestamp);

      if ($node->hasField('field_decision_section') && !$node->get('field_decision_section')->isEmpty()) {
        $decision_label = '§ ' . $node->field_decision_section->value . ' ' . $node->field_full_title->value;
      }
      else {
        $decision_label = $node->field_full_title->value;
      }

      $result = [
        'date_desktop' => date('d.m.Y', $timestamp),
        'date_mobile' => date('m - Y', $timestamp),
        'subject' => $decision_label,
        'link' => $node->toUrl()->toString(),
      ];

      if ($byYear) {
        $transformedResults[$year][] = $result;
      }
      else {
        $transformedResults[] = $result;
      }
    }

    return $transformedResults;
  }

  /**
   * Get policymaker composition based on latest meeting.
   *
   * @return array
   *   Policymaker composition, if found.
   */
  public function getComposition(): ?array {
    if (!$this->policymaker instanceof NodeInterface || !$this->policymakerId) {
      return [];
    }

    $query = \Drupal::entityQuery('node')
      ->condition('status', 1)
      ->condition('type', 'meeting')
      ->condition('field_meeting_dm_id', $this->policymakerId)
      ->condition('field_meeting_composition', '', '<>')
      ->range(0, 1)
      ->sort('field_meeting_date', 'DESC');

    $ids = $query->execute();

    if (empty($ids)) {
      return [];
    }

    $meeting = Node::load(reset($ids));
    if (!$meeting instanceof NodeInterface) {
      return [];
    }

    $composition = [];
    $allowed_roles = [
      'Jäsen',
      'Varajäsen',
      'Puheenjohtaja',
      'Varapuheenjohtaja',
    ];

    $names = [];
    foreach ($meeting->get('field_meeting_composition') as $field) {
      $data = json_decode($field->value, TRUE);
      if (!isset($data['Role']) || !in_array($data['Role'], $allowed_roles)) {
        continue;
      }
      if (!empty($data)) {
        $formatted_name = $this->formatTrusteeName($data['Name']);
        $names[] = $formatted_name;
        $composition[$formatted_name] = $data;

        // Special handling for combined last names or middle names.
        $alt_formatted_name = $this->formatTrusteeName($data['Name'], TRUE);
        if ($formatted_name !== $alt_formatted_name) {
          $names[] = $alt_formatted_name;
          $data['orig_name'] = $formatted_name;
          $data['alt_name'] = TRUE;
          $composition[$alt_formatted_name] = $data;
        }
      }
    }

    $results = [];
    $person_nodes = $this->getTrusteeNodesByName($names);
    foreach ($person_nodes as $node) {
      $name = $node->title->value;

      if ($node->hasField('field_trustee_image') && !$node->get('field_trustee_image')->isEmpty()) {
        $image_uri = $node->get('field_trustee_image')->first()->entity->getFileUri();
        $image_style = ImageStyle::load('1_1_thumbnail_2x');
        $image_url = $image_style->buildUrl($image_uri);
      }
      else {
        $image_url = NULL;
      }

      if (isset($composition[$name])) {
        $results[] = [
          'first_name' => $node->get('field_first_name')->value,
          'last_name' => $node->get('field_last_name')->value,
          'image_url' => $image_url,
          'url' => $node->toUrl()->setAbsolute(TRUE)->toString(),
          'role' => $composition[$name]['Role'],
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

      $results[] = [
        'first_name' => $first_name,
        'last_name' => $last_name,
        'image_url' => NULL,
        'url' => NULL,
        'role' => $data['Role'],
        'email' => NULL,
        'party' => NULL,
        'deputy_of' => $data['DeputyOf'],
      ];
    }
    return $results;
  }

  /**
   * Gets trustee node by agent ID.
   *
   * @param string $agent_id
   *   Agent ID.
   *
   * @return \Drupal\node\NodeInterface|null
   *   Trustee node, if found.
   */
  public function getTrusteeById(string $agent_id): ?NodeInterface {
    $query = \Drupal::entityQuery('node')
      ->condition('status', 1)
      ->condition('type', 'trustee')
      ->condition('field_trustee_id', $agent_id)
      ->range('0', 1);

    $ids = $query->execute();
    $id = reset($ids);
    if (empty($ids)) {
      return NULL;
    }

    return Node::load($id);
  }

  /**
   * Load trustee nodes based on names.
   *
   * @param array $names
   *   Names of trustees to load.
   *
   * @return array|null
   *   Array of nodes.
   */
  private function getTrusteeNodesByName(array $names): ?array {
    $query = \Drupal::entityQuery('node')
      ->condition('status', 1)
      ->condition('type', 'trustee')
      ->condition('title', $names, 'IN');

    $ids = $query->execute();

    if (empty($ids)) {
      return [];
    }

    return Node::loadMultiple($ids);
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
   * Get all API-retrieved minutes.
   *
   * @param int|null $limit
   *   Limit query.
   * @param bool $byYear
   *   Search by year.
   *
   * @return array
   *   Array of transcripts.
   */
  public function getApiMinutes(?int $limit = NULL, bool $byYear = FALSE): array {
    if (!$this->policymaker instanceof NodeInterface || !$this->policymakerId) {
      return [];
    }

    $query = \Drupal::entityQuery('node')
      ->condition('status', 1)
      ->condition('type', 'meeting')
      ->condition('field_meeting_dm_id', $this->policymakerId)
      ->condition('field_meeting_documents', '', '<>')
      ->sort('field_meeting_date', 'DESC');

    if ($limit) {
      $query->range('0', $limit);
    }

    $ids = $query->execute();

    if (empty($ids)) {
      return [];
    }

    $nodes = Node::loadMultiple($ids);
    /** @var \Drupal\paatokset_ahjo_api\Service\MeetingService $meetingService */
    $meetingService = \Drupal::service('paatokset_ahjo_meetings');

    $transformedResults = [];
    foreach ($nodes as $node) {
      $meeting_timestamp = strtotime($node->get('field_meeting_date')->value);
      $meeting_year = date('Y', $meeting_timestamp);
      $meeting_id = $node->get('field_meeting_id')->value;
      $decision_link = NULL;

      if ($document = $meetingService->getDocumentFromEntity($node, 'pöytäkirja')) {
        $document_title = t('Minutes');
      }
      elseif ($document = $meetingService->getDocumentFromEntity($node, 'esityslista')) {
        $document_title = t('Agenda');
        if (!$node->get('field_meeting_decision')->isEmpty()) {
          $decision_link = $this->getMinutesRoute($meeting_id);
        }
      }
      else {
        continue;
      }

      $result = [
        'publish_date' => date('d.m.Y', $meeting_timestamp),
        'publish_date_short' => date('m - Y', $meeting_timestamp),
        'title' => $document_title,
        'meeting_number' => $node->get('field_meeting_sequence_number')->value . ' - ' . $meeting_year,
        'origin_url' => $meetingService->getUrlFromAhjoDocument($document),
        'decision_link' => $decision_link,
      ];

      $link = $this->getMinutesRoute($meeting_id, NULL, FALSE);
      if ($link) {
        $result['link'] = $link;
      }

      if ($byYear) {
        $transformedResults[$meeting_year][] = $result;
      }
      else {
        $transformedResults[] = $result;
      }
    }

    return $transformedResults;
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
      return [];
    }

    $query = \Drupal::entityQuery('node')
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
      throw new NotFoundHttpException();
    }

    $meeting = Node::load(reset($ids));
    if (!$meeting instanceof NodeInterface) {
      throw new NotFoundHttpException();
    }

    $meeting_timestamp = strtotime($meeting->get('field_meeting_date')->value);

    $dateLong = date('d.m.Y', $meeting_timestamp);
    $meetingNumber = $meeting->get('field_meeting_sequence_number')->value;
    $meetingYear = date('Y', $meeting_timestamp);
    $policymaker_title = $this->policymaker->get('field_ahjo_title')->value;
    $agendaItems = NULL;
    $publishDate = NULL;
    $fileUrl = NULL;

    // Use either current language or fallback language for agenda items.
    $currentLanguage = \Drupal::languageManager()->getCurrentLanguage()->getId();
    if ($currentLanguage === 'fi') {
      $fallbackLanguage = 'sv';
    }
    else {
      $fallbackLanguage = 'fi';
    }

    /** @var \Drupal\paatokset_ahjo_api\Service\MeetingService $meetingService */
    $meetingService = \Drupal::service('paatokset_ahjo_meetings');

    if ($document = $meetingService->getDocumentFromEntity($meeting, 'pöytäkirja', $currentLanguage)) {
      $pageTitle = t('Minutes');
      $documentTitle = t('Minutes publication date');
    }
    elseif ($document = $meetingService->getDocumentFromEntity($meeting, 'esityslista', $currentLanguage)) {
      $pageTitle = t('Agenda');
      $documentTitle = t('Agenda publication date');
    }
    else {
      $pageTitle = t('Meeting');
      $documentTitle = NULL;
    }

    if ($document instanceof MediaInterface) {
      if ($document->hasField('field_document_issued') && !$document->get('field_document_issued')->isEmpty()) {
        $document_timestamp = strtotime($document->get('field_document_issued')->value);
        $publishDate = date('d.m.Y', $document_timestamp);
      }
      else {
        $publishDate = NULL;
      }

      $fileUrl = $meetingService->getUrlFromAhjoDocument($document);
    }

    // Get items in both languages because all aren't translated.
    $agendaItems = $this->getAgendaItems($meeting->get('field_meeting_agenda'), $meetingId, $currentLanguage);
    $fallbackAgendaItems = $this->getAgendaItems($meeting->get('field_meeting_agenda'), $meetingId, $fallbackLanguage);

    // If the default language list is missing items, add them from fallback.
    if (count($agendaItems) <= count($fallbackAgendaItems)) {
      // Initiate new list to keep correct ordering.
      $newList = [];
      foreach ($fallbackAgendaItems as $key => $item) {
        if (isset($agendaItems[$key])) {
          $newList[$key] = $agendaItems[$key];
        }
        else {
          $newList[$key] = $fallbackAgendaItems[$key];
        }
      }
      $agendaItems = $newList;
    }

    $decisionAnnouncement = $this->getDecisionAnnouncement($meeting);

    return [
      'meeting' => [
        'nid' => $meeting->id(),
        'page_title' => $pageTitle,
        'date_long' => $dateLong,
        'title' => $policymaker_title . ' ' . $meetingNumber . '/' . $meetingYear,
      ],
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
    /** @var \Drupal\paatokset_ahjo_api\Service\CaseService $caseService */
    $caseService = \Drupal::service('paatokset_ahjo_cases');

    $agendaItems = [];
    $agendaItemsLast = [];
    $last_count = 0;
    foreach ($list_field as $item) {

      $data = json_decode($item->value, TRUE);

      if (!$data) {
        continue;
      }

      if ($data['PDF']['Language'] !== $langcode) {
        continue;
      }

      if (!empty($data['Section']) && !empty($data['AgendaPoint']) && $data['AgendaPoint'] !== 'null') {
        $index = t('Case @point. / @section', [
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

      $agenda_link = NULL;
      if (!empty($data['Section']) && !empty($data['AgendaItem'])) {
        $section_clean = (string) intval($data['Section']);
        $agenda_link = $caseService->getDecisionUrlByTitle($data['AgendaItem'], $meeting_id, $section_clean);
      }
      else {
        $agenda_link = $caseService->getDecisionUrlByTitle($data['AgendaItem'], $meeting_id);
      }

      if (empty($data['AgendaPoint']) || $data['AgendaPoint'] === 'null') {
        $last_count++;
        $id = 'x-' . $last_count . '-' . $data['Section'];
        $agendaItemsLast[$id] = [
          'subject' => $data['AgendaItem'],
          'index' => $index,
          'link' => $agenda_link,
        ];
      }
      else {
        $id = $data['AgendaPoint'] . '-' . $data['Section'];
        $agendaItems[$id] = [
          'subject' => $data['AgendaItem'],
          'index' => $index,
          'link' => $agenda_link,
        ];
      }
    }

    return array_merge($agendaItems, $agendaItemsLast);
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
  public function getMinutesOfDiscussion(?int $limit = NULL, bool $byYear = FALSE, string $meetingId = NULL) : array {
    if (!$this->policymaker instanceof NodeInterface || !$this->policymakerId) {
      return [];
    }

    $query = \Drupal::entityQuery('node')
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
    $nodes = Node::loadMultiple($meeting_ids);

    $transformedResults = [];
    foreach ($meeting_minutes as $meeting_id => $meeting) {
      foreach ($meeting as $entity) {
        $meeting_timestamp = strtotime($nodes[$meeting_id]->get('field_meeting_date')->value);
        $meeting_year = date('Y', $meeting_timestamp);
        $dateLong = date('d.m.Y', $meeting_timestamp);
        $dateShort = date('m - Y', $meeting_timestamp);

        $result = [
          'publish_date' => $dateLong,
          'publish_date_short' => $dateShort,
          'title' => $entity->label(),
        ];

        $link = $this->getMinutesRoute($nodes[$meeting_id]->get('field_meeting_id')->value);
        if ($link) {
          $result['link'] = $link;
        }

        if ($entity->get('field_document')->target_id) {
          $file_id = $entity->get('field_document')->target_id;
          $download_link = \Drupal::service('file_url_generator')->generateAbsoluteString(File::load($file_id)->getFileUri());
          $result['origin_url'] = $download_link;
        }

        if ($byYear) {
          $transformedResults[$meeting_year][] = $result;
        }
        else {
          $transformedResults[] = $result;
        }
      }
    }

    return $transformedResults;
  }

  /**
   * Get policymaker-related declarations of affiliation.
   *
   * @return array
   *   Array of resulting documents
   */
  private function getDeclarationsOfAffilition() {
    $ids = \Drupal::entityQuery('media')
      ->condition('bundle', 'declaration_of_affiliation')
      ->condition('field__policymaker_reference', $this->policymaker->id())
      ->execute();

    return Media::loadMultiple($ids);
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

    $ids = \Drupal::entityQuery('media')
      ->condition('bundle', 'minutes_of_the_discussion')
      ->condition('field_meetings_reference', $meetingids, 'IN')
      ->execute();
    $entities = Media::loadMultiple($ids);

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
    $nids = \Drupal::entityQuery('node')
      ->condition('type', 'trustee')
      ->condition('status', 1)
      ->condition('field_trustee_initiatives', '', '<>')
      ->execute();

    $nodes = Node::loadMultiple($nids);
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
      return 'Kaupunginhallituksen jaosto';
    }

    // Return NULL if organization type field is empty and no override is set.
    if (!$node->hasField('field_organization_type') || $node->get('field_organization_type')->isEmpty()) {
      return NULL;
    }

    // Check org type field.
    return $this->getPolicymakerType($node->get('field_organization_type')->value);
  }

  /**
   * Get display version of organization type.
   *
   * @param string $type
   *   Org type to check.
   *
   * @return string
   *   Either the same type that was passed in or an altered version.
   */
  public function getPolicymakerType(string $type): string {
    $output = NULL;

    switch (strtolower($type)) {
      case 'viranhaltija':
        $output = 'Viranhaltijat';
        break;

      case 'luottamushenkilö':
        $output = 'Luottamushenkilöpäättäjät';
        break;

      case 'lautakunta':
      case 'jaosto':
      case 'toimi-/neuvottelukunta':
        $output = 'Lautakunnat ja jaostot';
        break;

      default:
        $output = $type;
        break;
    }

    return $output;
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
   * Gets policymaker color coding from node.
   *
   * @param Drupal\node\NodeInterface|null $node
   *   Policymaker node. Leave empty to use set policymaker.
   *
   * @return string
   *   Color code for label.
   */
  public function getPolicymakerClass(?NodeInterface $node = NULL): string {
    if ($node === NULL) {
      $node = $this->getPolicyMaker();
    }
    if (!$node instanceof NodeInterface) {
      return 'color-none';
    }

    // First check overridden color code.
    if ($node->hasField('field_organization_color_code') && !$node->get('field_organization_color_code')->isEmpty()) {
      return $node->get('field_organization_color_code')->value;
    }

    if ($node->hasField('field_city_council_division') && $node->get('field_city_council_division')->value) {
      return 'color-hopea';
    }

    // If type isn't set, return with no color.
    if (!$node->hasField('field_organization_type') || $node->get('field_organization_type')->isEmpty()) {
      return 'color-none';
    }

    // Use org type to determine color coding.
    switch (strtolower($node->get('field_organization_type')->value)) {
      case 'valtuusto':
        $color = 'color-kupari';
        break;

      case 'hallitus':
        $color = 'color-hopea';
        break;

      case 'viranhaltija':
        $color = 'color-suomenlinna';
        break;

      case 'luottamushenkilö':
        $color = 'color-engel';
        break;

      case 'lautakunta':
      case 'toimi-/neuvottelukunta':
      case 'jaosto':
        $color = 'color-sumu';
        break;

      default:
        $color = 'color-none';
        break;
    }

    return $color;
  }

  /**
   * Gets policymaker color coding by ID.
   *
   * @param string $id
   *   Policymaker ID.
   *
   * @return string
   *   Color code for label.
   */
  public function getPolicymakerClassById(string $id): string {
    $node = $this->getPolicyMaker($id);
    if ($node instanceof NodeInterface) {
      return $this->getPolicyMakerClass($node);
    }
    return 'color-none';
  }

}
