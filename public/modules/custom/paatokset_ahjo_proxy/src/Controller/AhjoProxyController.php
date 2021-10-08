<?php

declare(strict_types = 1);

namespace Drupal\paatokset_ahjo_proxy\Controller;

use Drupal\Core\Url;
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
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON data for meetings.
   */
  public function meetings(Request $request): JsonResponse {
    $query_string = $request->getQueryString();
    $data = $this->ahjoProxy->getMeetings($query_string);
    return new JsonResponse($data);
  }

}
