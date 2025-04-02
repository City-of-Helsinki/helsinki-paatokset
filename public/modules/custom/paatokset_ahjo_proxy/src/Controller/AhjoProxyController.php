<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_proxy\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Language\LanguageManager;
use Drupal\paatokset_ahjo_proxy\AhjoProxy;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * AHJO proxy page controller.
 *
 * @package Drupal\paatokset_ahjo_proxy\Controller
 */
class AhjoProxyController extends ControllerBase {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManager
   */
  protected $languageManager;

  /**
   * Ahjo proxy service.
   *
   * @var \Drupal\paatokset_ahjo_proxy\AhjoProxy
   */
  protected $ahjoProxy;

  /**
   * Constructor.
   */
  public function __construct(LanguageManager $language_manager, AhjoProxy $ahjo_proxy) {
    $this->ahjoProxy = $ahjo_proxy;
    $this->languageManager = $language_manager;
  }

  /**
   * Create and inject.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('language_manager'),
      $container->get('paatokset_ahjo_proxy')
    );
  }

  /**
   * Return meetings data.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON data for meetings.
   */
  public function meetings(Request $request): JsonResponse {
    $query_string = $request->getQueryString();
    $data = $this->ahjoProxy->getMeetings($query_string);
    if (empty($data)) {
      throw new NotFoundHttpException();
    }
    return new JsonResponse($data);
  }

  /**
   * Return cases data.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON data for cases.
   */
  public function cases(Request $request): JsonResponse {
    $query_string = $request->getQueryString();
    $data = $this->ahjoProxy->getCases($query_string);
    if (empty($data)) {
      throw new NotFoundHttpException();
    }
    return new JsonResponse($data);
  }

  /**
   * Return decisions data.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON data for decisions.
   */
  public function decisions(Request $request): JsonResponse {
    $query_string = $request->getQueryString();
    $data = $this->ahjoProxy->getDecisions($query_string);
    if (empty($data)) {
      throw new NotFoundHttpException();
    }
    return new JsonResponse($data);
  }

  /**
   * Get data for a single meeting.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP request.
   * @param string $id
   *   Meeting ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON data for meetings.
   */
  public function meetingsSingle(Request $request, string $id): JsonResponse {
    $query_string = $request->getQueryString();
    $data = $this->ahjoProxy->getSingleMeeting($id, $query_string);
    return new JsonResponse($data);
  }

  /**
   * Get data for a agenda item on a meeting.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP request.
   * @param string $meeting_id
   *   Meeting ID.
   * @param string $id
   *   Agenda item document ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON data for agenda item.
   */
  public function agendaItem(Request $request, string $meeting_id, string $id): JsonResponse {
    $query_string = $request->getQueryString();
    $data = $this->ahjoProxy->getAgendaItem($meeting_id, $id, $query_string);
    return new JsonResponse($data);
  }

  /**
   * Get data for a single decision.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP request.
   * @param string $id
   *   Native ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON data for decisions.
   */
  public function decisionsSingle(Request $request, string $id): JsonResponse {
    $query_string = $request->getQueryString();
    $data = $this->ahjoProxy->getSingleDecision($id, $query_string);
    return new JsonResponse($data);
  }

  /**
   * Get data for a single case.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP request.
   * @param string $id
   *   Case ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON data for cases.
   */
  public function casesSingle(Request $request, string $id): JsonResponse {
    $query_string = $request->getQueryString();
    $data = $this->ahjoProxy->getSingleCase($id, $query_string);
    return new JsonResponse($data);
  }

  /**
   * Get data for a single trustee.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP request.
   * @param string $id
   *   Agent ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON data for trustees.
   */
  public function trusteesSingle(Request $request, string $id): JsonResponse {
    $query_string = $request->getQueryString();
    $data = $this->ahjoProxy->getSingleTrustee($id, $query_string);
    return new JsonResponse($data);
  }

  /**
   * Get data for a single organization.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP request.
   * @param string $id
   *   Organization ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON data for organizations.
   */
  public function organizationSingle(Request $request, string $id): JsonResponse {
    $query_string = $request->getQueryString();
    $data = $this->ahjoProxy->getSingleOrganization($id, $query_string);
    return new JsonResponse($data);
  }

  /**
   * Get data for a single decisionmaker organization.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP request.
   * @param string $id
   *   Organization ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON data for organizations.
   */
  public function decisionmakerSingle(Request $request, string $id): JsonResponse {
    $query_string = $request->getQueryString();
    $data = $this->ahjoProxy->getSingleDecisionmaker($id, $query_string);
    return new JsonResponse($data);
  }

  /**
   * Get organization positions of trust.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP request.
   * @param string $id
   *   Organization ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON data for organizations.
   */
  public function organizationPositions(Request $request, string $id): JsonResponse {
    $query_string = $request->getQueryString();
    $data = $this->ahjoProxy->getOrganizationPositions($id, $query_string);
    return new JsonResponse($data);
  }

  /**
   * Returns aggregated data.
   *
   * @param string $dataset
   *   Which aggregated dataset to fetch (meetings_all, cases_latest, etc).
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Aggregated JSON data.
   */
  public function getAggregatedData(string $dataset): JsonResponse {
    $data = $this->ahjoProxy->getAggregatedData($dataset);
    return new JsonResponse($data);
  }

  /**
   * Return file from Ahjo API.
   *
   * @param string $nativeId
   *   Native ID.
   *
   * @return GuzzleHttp\Psr7\Response
   *   Response to HTTP request from Ahjo API.
   */
  public function getFile(string $nativeId): Response {
    /** @var \GuzzleHttp\Psr7\Response $response */
    $response = $this->ahjoProxy->getFile($nativeId);
    if (!$response) {
      throw new NotFoundHttpException();
    }

    return $response;
  }

  /**
   * Get data for a single record.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP request.
   * @param string $nativeId
   *   Native ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON data for record.
   */
  public function getRecord(Request $request, string $nativeId): JsonResponse {
    $query_string = $request->getQueryString();
    $data = $this->ahjoProxy->getRecord($nativeId, $query_string);
    return new JsonResponse($data);
  }

}
