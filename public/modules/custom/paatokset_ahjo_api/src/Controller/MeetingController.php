<?php

namespace Drupal\paatokset_ahjo_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;

/**
 * Controller for retrieving meeting data.
 */
class MeetingController extends ControllerBase {
  /**
   * Instance of MeetingService.
   *
   * @var \Drupal\paatokset_ahjo_api\Service\MeetingService
   */
  private $meetingService;

  /**
   * Class constuctor.
   */
  public function __construct() {
    $this->meetingService = \Drupal::service('paatokset_ahjo_meetings');
  }

  /**
   * Retrieves matching meeting data.
   *
   * See MeetingService class for parameter definition.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Automatically injected Request object.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Return response with queried data or errors.
   */
  public function query(Request $request) : Response {
    $params = [
      'only_future_cancelled' => TRUE,
    ];

    $allowedParams = [
      'from',
      'to',
      'agenda_published',
      'minutes_published',
      'policymaker',
    ];

    foreach ($allowedParams as $param) {
      if ($request->query->get($param)) {
        $params[$param] = $request->query->get($param);
      }
    }

    try {
      $meetings = $this->meetingService->query($params);
    }
    catch (\throwable $error) {
      return new Response(
        json_encode([
          'errors' => $error->getMessage(),
        ]),
        Response::HTTP_BAD_REQUEST
      );
    }

    $data = [
      'data' => $meetings,
      '#cache' => [
        'max-age' => -1,
        'contexts' => [
          'url',
        ],
        'tags' => ['node_list:meeting'],
      ],
    ];

    $response = new CacheableJsonResponse($data);
    $response->addCacheableDependency(CacheableMetadata::createFromRenderArray($data));
    return $response;
  }

}
