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
use Drupal\paatokset_ahjo\Entity\Issue;
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

    if (!$node instanceof NodeInterface) {
      $current_path = \Drupal::service('path.current')->getPath();
      $path_parts = explode('/', trim($current_path));
      array_pop($path_parts);
      $path_alias = implode('/', $path_parts);
      $path = \Drupal::service('path_alias.manager')->getPathByAlias($path_alias);
      if (preg_match('/node\/(\d+)/', $path, $matches)) {
        $node = Node::load($matches[1]);
      }
    }

    // Determine policymaker in custom routes.
    if (!$node instanceof NodeInterface && $routeParams->get('organization')) {
      array_pop($path_parts);
      $path = \Drupal::service('path_alias.manager')->getPathByAlias(implode('/', $path_parts));

      if (preg_match('/node\/(\d+)/', $path, $matches)) {
        $node = Node::load($matches[1]);
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
    if ($this->policymaker->get('field_organization_type')->value === 'Luottamushenkilö') {
      return NULL;
    }

    $routes = PolicymakerRoutes::getOrganizationRoutes();
    $baseRoute = $routes['documents'];
    $currentLanguage = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $localizedRoute = "$baseRoute.$currentLanguage";

    if ($this->routeExists($localizedRoute)) {
      return Url::fromRoute($localizedRoute, ['organization' => strtolower($this->policymaker->get('field_ahjo_title')->value)]);
    }

    return NULL;
  }

  /**
   * Return route for policymaker decisions.
   *
   * @return Drupal\Core\Url|null
   *   URL object, if route is valid.
   */
  public function getDecisionsRoute(): ?Url {
    if ($this->policymaker->get('field_organization_type')->value !== 'Luottamushenkilö') {
      return NULL;
    }

    $routes = PolicymakerRoutes::getTrusteeRoutes();
    $baseRoute = $routes['decisions'];
    $currentLanguage = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $localizedRoute = "$baseRoute.$currentLanguage";

    if ($this->routeExists($localizedRoute)) {
      return Url::fromRoute($localizedRoute, ['organization' => strtolower($this->policymaker->get('field_ahjo_title')->value)]);
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
   *
   * @return Drupal\Core\Url|null
   *   URL object, if route is valid.
   */
  public function getMinutesRoute(string $id, ?string $policymaker_id = NULL): ?Url {
    if (!empty($policymaker_id)) {
      $this->setPolicyMaker($policymaker_id);
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

    if ($this->routeExists($localizedRoute)) {
      return Url::fromRoute($localizedRoute, [
        'organization' => strtolower($policymaker_org),
        'id' => $id,
      ]);
    }
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
   * Get all the decisions for one classification code.
   *
   * @return array
   *   of results.
   */
  public function getAgendasList($byYear = TRUE, $limit = NULL): array {
    $database = \Drupal::database();
    $query = $database->select('paatokset_agenda_item_field_data', 'aifd')
      ->fields('aifd', [
        'subject',
        'meeting_date',
        'meeting_number',
        'id',
        'issue_id',
      ]);
    $query->condition('meeting_policymaker_link', $this->policymaker->get('field_resource_uri')->value, '=');
    $query->orderBy('meeting_date', 'DESC');

    if ($byYear) {
      $query->addExpression('YEAR(meeting_date)', 'year');
    }

    if ($limit) {
      $query->range(0, $limit);
    }

    $queryResult = $query->execute()->fetchAll();

    $results = [];
    foreach ($queryResult as $row) {
      $longDate = date('d.m.Y', strtotime($row->meeting_date));
      $shortDate = date('m - Y', strtotime($row->meeting_date));
      $results[$row->id] = [
        'date_desktop' => $longDate,
        'date_mobile' => $shortDate,
        'subject' => $row->subject,
        'issue_id' => $row->issue_id,
      ];

      if ($byYear) {
        $results[$row->id]['year'] = $row->year;
      }
    }

    $issue_ids = array_column($results, 'issue_id');
    $issues = Issue::loadMultiple($issue_ids);
    $issue_links = [];
    if (!empty($issues)) {
      foreach ($issues as $issue) {
        $issue_links[$issue->get('id')->value] = $issue->toUrl();
      }
    }

    $transformedResults = [];
    foreach ($results as $id => $result) {
      if (isset($issue_links[$result['issue_id']])) {
        $result['link'] = $issue_links[$result['issue_id']]->setOption('query', ['decision' => $id])->toString();
      }

      if ($byYear) {
        $transformedResults[$result['year']][] = $result;
      }
      else {
        $transformedResults[] = $result;
      }
    }

    return $transformedResults;
  }

  /**
   * Get all the decisions for one policymaker id.
   *
   * @return array
   *   of results.
   */
  public function getAgendasYears(): array {
    $database = \Drupal::database();
    $query = $database->select('paatokset_agenda_item_field_data', 'aifd')
      ->condition('meeting_policymaker_link', $this->policymaker->get('field_resource_uri')->value);
    $query->fields('aifd', ['meeting_policymaker_link']);
    $query->addExpression('YEAR(meeting_date)', 'date');
    $query->groupBy('date');
    $query->orderBy('date', 'DESC');
    $queryResult = $query->distinct()->execute()->fetchAll();
    $result = [];

    foreach ($queryResult as $row) {
      $result[$row->date][] = [
        '#type' => 'link',
        '#title' => $row->date,
      ];
    }

    return $result;
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

      if ($document = $meetingService->getDocumentFromEntity($node, 'pöytäkirja')) {
        $document_title = t('Minutes');
      }
      elseif ($document = $meetingService->getDocumentFromEntity($node, 'esityslista')) {
        $document_title = t('Agenda');
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
      ];

      $link = $this->getMinutesRoute($meeting_id);
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

    // Only get finnish agenda points for now and use swedish as fallback.
    // @todo Get current and fallback languages dynamically.
    $currentLanguage = 'fi';
    $fallbackLanguage = 'sv';

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

    $agendaItems = $this->getAgendaItems($meeting->get('field_meeting_agenda'), $currentLanguage);
    if (empty($agendaItems)) {
      $agendaItems = $this->getAgendaItems($meeting->get('field_meeting_agenda'), $fallbackLanguage);
    }

    return [
      'meeting' => [
        'page_title' => $pageTitle,
        'date_long' => $dateLong,
        'title' => $policymaker_title . ' ' . $meetingNumber . '/' . $meetingYear,
      ],
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
   * @param string $langcode
   *   Langcode to use.
   *
   * @return array
   *   Array of agenda items. Can be empty.
   */
  private function getAgendaItems(FieldItemListInterface $list_field, string $langcode = 'fi'): array {
    $agendaItems = [];
    foreach ($list_field as $item) {

      $data = json_decode($item->value, TRUE);

      if (!$data) {
        continue;
      }

      if ($data['PDF']['Language'] !== $langcode) {
        continue;
      }

      // Get PDF for now, should be switched to issue URL later.
      if (isset($data['PDF']['NativeId'])) {
        $agenda_link = Url::fromRoute('paatokset_ahjo_proxy.get_file', ['nativeId' => $data['PDF']['NativeId']], ['absolute' => TRUE])->toString();
      }
      else {
        $agenda_link = NULL;
      }

      if (!empty($data['Section'])) {
        $index = $data['AgendaPoint'] . '. – ' . $data['Section'];
      }
      else {
        $index = $data['AgendaPoint'] . '.';
      }

      $agendaItems[] = [
        'subject' => $data['AgendaItem'],
        'index' => $index,
        'link' => $agenda_link,
      ];
    }

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
          $download_link = file_create_url(File::load($file_id)->getFileUri());
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
   * Get all policymaker documents.
   *
   * @return array
   *   Array of resulting documents
   */
  public function getDocumentData() : array {
    $documents = $this->getMinutesOfDiscussion();
    $declarationsOfAffiliation = $this->getDeclarationsOfAffilition();

    foreach ($declarationsOfAffiliation as $declaration) {
      $date = $declaration->get('created')->value;
      $title = $declaration->name->value;
      $year = date('Y', $date);
      if (!isset($documents['years'])) {
        $documents['years'][$year] = [
          '#type' => 'link',
          '#title' => $year,
        ];
      }

      $file_id = $declaration->get('field_document')->target_id;
      if ($declaration->get('field_document')->target_id) {
        $download_link = Url::fromUri(file_create_url(File::load($file_id)->getFileUri()));
      }

      $documents['list'][$year][] = [
        '#type' => 'link',
        '#responsiveDate' => date("m-Y", $date),
        '#responsiveTitle' => $title,
        '#date' => date("d.m.Y", $date),
        '#timestamp' => $date,
        '#year' => $year,
        '#title' => $title,
        '#url' => '',
        '#download_link' => $download_link ?? NULL,
        '#download_label' => str_replace(' ', '_', $title),
      ];
    }

    return $documents;
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
   * Get organization display type from node.
   * This shouldn't be used for type checking, only for displayed labels.
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
   * Get altered or default organization type.
   * This shouldn't be used for type checking, only for displayed labels.
   *
   * @param string $type
   *   Org type to check.
   *
   * @return string
   *   Either the same type that was passed in or an altered version.
   */
  public function getPolicymakerType(string $type): string {
    switch(strtolower($type)) {
      case 'viranhaltija':
        return 'Viranhaltijat';
        break;

      case 'luottamushenkilö':
        return 'Luottamushenkilöpäättäjät';
        break;

      case 'lautakunta':
      case 'jaosto':
      case 'toimi-/neuvottelukunta':
        return 'Lautakunnat ja jaostot';
        break;

      default:
        return $type;
        break;
    }


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
