<?php

namespace Drupal\paatokset_ahjo_api\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\paatokset_ahjo_api\Service\MeetingService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for retrieving meeting data.
 */
class MeetingController extends ControllerBase {

  /**
   * {@inheritDoc}
   */
  public function __construct(protected MeetingService $meetingService) {
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('paatokset_ahjo_meetings')
    );
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
      $meetings = $this->meetingService->elasticQuery($params);
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
        'tags' => ['search_api_list:meetings'],
      ],
    ];

    $response = new CacheableJsonResponse($data);
    $response->addCacheableDependency(CacheableMetadata::createFromRenderArray($data));
    return $response;
  }

}
