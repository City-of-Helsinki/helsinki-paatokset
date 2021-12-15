<?php

namespace Drupal\paatokset_policymakers\Service;

use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\Component\Utility\Html;
use Drupal\node\NodeInterface;
use Drupal\paatokset_ahjo\Entity\Issue;
use Drupal\paatokset_ahjo\Entity\Meeting;
use Drupal\paatokset_ahjo\Entity\MeetingDocument;
use Drupal\paatokset_policymakers\Enum\PolicymakerRoutes;

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

    return reset($queryResult);
  }

  /**
   * Set policy maker by ID.
   *
   * @param string $id
   *   Policy maker ID.
   */
  public function setPolicyMaker(string $id): void {
    $this->policymaker = $this->getPolicyMaker($id);
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

    $this->policymaker = $node;
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
    if ($this->policymaker->get('field_organization_type')->value === 'trustee') {
      return NULL;
    }

    $routes = PolicymakerRoutes::getOrganizationRoutes();
    $baseRoute = $routes['documents'];
    $currentLanguage = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $localizedRoute = "$baseRoute.$currentLanguage";

    if ($this->routeExists($localizedRoute)) {
      return Url::fromRoute($localizedRoute, ['organization' => strtolower($this->policymaker->get('title')->value)]);
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
    if ($this->policymaker->get('field_organization_type')->value !== 'trustee') {
      return NULL;
    }

    $routes = PolicymakerRoutes::getTrusteeRoutes();
    $baseRoute = $routes['decisions'];
    $currentLanguage = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $localizedRoute = "$baseRoute.$currentLanguage";

    if ($this->routeExists($localizedRoute)) {
      return Url::fromRoute($localizedRoute, ['organization' => strtolower($this->policymaker->get('title')->value)]);
    }

    return NULL;
  }

  /**
   * Return minutes page route by meeting id.
   *
   * @return Drupal\Core\Url|null
   *   URL object, if route is valid.
   */
  public function getMinutesRoute($id): ?Url {
    if ($this->policymaker->get('field_organization_type')->value === 'trustee') {
      return NULL;
    }

    $route = PolicymakerRoutes::getSubroutes()['minutes'];
    $currentLanguage = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $localizedRoute = "$route.$currentLanguage";

    if ($this->routeExists($localizedRoute)) {
      return Url::fromRoute($localizedRoute, [
        'organization' => strtolower($this->policymaker->get('title')->value),
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
    $database = \Drupal::database();
    $query = $database->select('paatokset_meeting_document_field_data', 'pmdfd')
      ->fields('pmdfd', ['origin_url', 'publish_time', 'meeting_id'])
      ->fields('pmfd', ['meeting_date', 'number']);
    $query->join('paatokset_meeting_field_data', 'pmfd', ' pmfd.id = pmdfd.meeting_id');
    $query->condition('pmfd.policymaker_uri', $this->policymaker->get('field_resource_uri')->value);
    $query->orderBy('pmdfd.publish_time', 'DESC');

    if ($limit) {
      $query->range(0, $limit);
    }

    if ($byYear) {
      $query->addExpression('YEAR(pmdfd.publish_time)', 'year');
    }

    $results = $query->execute()->fetchAll();

    $transformedResults = [];
    foreach ($results as $result) {
      $longDate = date('d.m.Y', strtotime($result->publish_time));
      $shortDate = date('m - Y', strtotime($result->publish_time));
      $transformedResult = [
        'publish_date' => $longDate,
        'publish_date_short' => $shortDate,
        'title' => t('Minutes'),
        'origin_url' => $result->origin_url,
        'meeting_number' => $result->number . ' - ' . date('Y', strtotime($result->publish_time)),
      ];

      $link = $this->getMinutesRoute($result->meeting_id);
      if ($link) {
        $transformedResult['link'] = $link;
      }

      if ($byYear) {
        $transformedResults[$result->year][] = $transformedResult;
      }
      else {
        $transformedResults[] = $transformedResult;
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
    // Check that the meeting belongs to current policymaker.
    $query = \Drupal::entityQuery('paatokset_meeting')
      ->condition('id', $meetingId)
      ->condition('policymaker_uri', $this->policymaker->get('field_resource_uri')->value)
      ->range(0, 1)
      ->execute();

    $id = reset($query);

    if ($id) {
      $meeting = Meeting::load($id);

      $database = \Drupal::database();
      $query = $database->select('paatokset_agenda_item_field_data', 'paifd')
        ->fields('paifd', ['id', 'subject', 'index', 'issue_id']);
      $query->condition('paifd.meeting_id', $id);
      $query->addExpression('cast(paifd.index as unsigned)', 'cast_index');
      $query->orderBy('cast_index');
      $agendaItems = $query->execute()->fetchAllAssoc('id');

      $issue_ids = array_map(function ($item) {
        return $item->issue_id;
      }, $agendaItems);

      $issues = Issue::loadMultiple($issue_ids);

      foreach ($agendaItems as $agendaItem) {
        if (isset($issue[$agendaItem->issue_id])) {
          $agendaItem->link = $issues[$agendaItem->issue_id]->toUrl()->setOptions(['query' => ['decision' => $agendaItem->id]]);
        }
      }

      $dateLong = date('d.m.Y', strtotime($meeting->get('meeting_date')->value));
      $dateShort = date('m/Y', strtotime($meeting->get('meeting_date')->value));
      $policymaker = $this->policymaker->get('title')->value;

      $documentId = \Drupal::entityQuery('paatokset_meeting_document')
        ->condition('meeting_id', $id)
        ->execute();

      $fileUrl = NULL;
      $publishDate = NULL;
      if ($documentId) {
        $document = MeetingDocument::load(reset($documentId));
        $fileUrl = $document->get('origin_url')->value;
        $publishDate = date('d.m.Y', strtotime($document->get('publish_time')->value));
      }

      return [
        'meeting' => [
          'date_long' => $dateLong,
          'title' => "$policymaker $dateShort",
        ],
        'list' => $agendaItems,
        'file' => [
          'file_url' => $fileUrl,
          'publish_date' => $publishDate,
        ],
      ];
    }

    return NULL;
  }

  /**
   * Get all meeting document-related data.
   *
   * @param int|null $limit
   *   Limit query.
   * @param bool $byYear
   *   Search by year.
   *
   * @return array
   *   Meeting document data.
   */
  public function getMinutesOfDiscussion(?int $limit = NULL, bool $byYear = FALSE, string $meetingId = NULL) : array {
    $database = \Drupal::database();
    $query = $database->select('paatokset_meeting_field_data', 'pmfd')
      ->fields('pmfd', ['id', 'meeting_date']);
    $query->orderBy('meeting_date', 'DESC');
    $query->condition('policymaker_uri', $this->policymaker->get('field_resource_uri')->value, '=');

    if ($limit) {
      $query->range(0, $limit);
    }

    if ($meetingId) {
      $query->condition('id', $meetingId);
    }

    $result = $query->execute()->fetchAllKeyed();
    $mediaEntities = $this->getMeetingMediaEntities(array_keys($result));
    $transformedResults = [];
    foreach ($mediaEntities as $id => $meeting) {
      foreach ($meeting as $entity) {
        $file_id = $entity->get('field_document')->target_id;
        $download_link = NULL;
        if ($entity->get('field_document')->target_id) {
          $download_link = Url::fromUri(file_create_url(File::load($file_id)->getFileUri()))->toString();
        }
        $year = date('Y', strtotime($result[$id]));
        $transformedResult = [
          'publish_date' => date('d.m.Y', strtotime($result[$id])),
          'publish_date_short' => date('m-Y', strtotime($result[$id])),
          'title' => $entity->get('name')->value,
          'origin_url' => $download_link,
        ];

        if ($byYear) {
          $transformedResults[$year][] = $transformedResult;
        }
        else {
          $transformedResults[] = $transformedResult;
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

}
