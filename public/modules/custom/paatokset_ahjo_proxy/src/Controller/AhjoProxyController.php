<?php

declare(strict_types = 1);

namespace Drupal\paatokset_ahjo_proxy\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\paatokset_ahjo_proxy\AhjoProxy;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * AHJO proxy page controller.
 *
 * @package Drupal\paatokset_ahjo_proxy\Controller
 */
class AhjoProxyController extends ControllerBase {

  /**
   * Ahjo proxy service.
   *
   * @var \Drupal\paatokset_ahjo_proxy\AhjoProxy
   */
  protected $ahjoProxy;

  /**
   * Constructor.
   */
  public function __construct(AhjoProxy $ahjo_proxy) {
    $this->ahjoProxy = $ahjo_proxy;
  }

  /**
   * Create and inject.
   */
  public static function create(ContainerInterface $container) {
    return new static(
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
   * Get data for a single case.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP request.
   * @param string $id
   *   Meeting ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON data for meetings.
   */
  public function casesSingle(Request $request, string $id): JsonResponse {
    $query_string = $request->getQueryString();
    $data = $this->ahjoProxy->getSingleCase($id, $query_string);
    return new JsonResponse($data);
  }

  /**
   * Returns aggregated data.
   *
   * @param string $dataset
   *   Which aggregated dataset to fetch (meetins_all, cases_latest, etc).
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Aggregated JSON data.
   */
  public function getAggregatedData(string $dataset): JsonResponse {
    $data = $this->ahjoProxy->getAggregatedData($dataset);
    return new JsonResponse($data);
  }

}
