<?php

declare(strict_types = 1);

namespace Drupal\paatokset_search\Controller;

use Drupal\Core\Controller\ControllerBase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Search proxy controller.
 *
 * @package Drupal\paatokset_search\Controller
 */
class SearchProxyController extends ControllerBase {

  /**
   * HTTP Client.
   *
   * @var GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Constructor.
   */
  public function __construct(ClientInterface $http_client) {
    $this->httpClient = $http_client;
  }

  /**
   * Create and inject.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client'),
    );
  }

  /**
   * Send request to Elastic proxy.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP request.
   * @param string $index
   *   Index to search from (paatokset_decisions or paatokset_policymakers).
   * @param string $url
   *   URL to search from.
   *
   * @return GuzzleHttp\Psr7\Response
   *   Response from Elastic proxy.
   */
  public function searchRequest(Request $request, string $index, string $url): Response {
    $base_url = getenv('REACT_APP_ELASTIC_URL');

    if (!$base_url) {
      throw new NotFoundHttpException();
    }

    $url = $base_url . '/' . $index . '/' . $url;

    $query_string = $request->getQueryString();
    if ($query_string) {
      $url .= '?' . $query_string;
    }

    $method = $request->getMethod();
    $payload = (string) $request->getContent();

    /** @var \GuzzleHttp\Psr7\Response $response */
    try {
      $response = $this->httpClient->request($method, $url,
      [
        'headers' => [
          'Content-type' => 'application/json',
        ],
        'http_errors' => FALSE,
        'body' => $payload,
      ]);
    }
    catch (\Exception $e) {
    }

    if (!$response) {
      throw new NotFoundHttpException();
    }

    return $response;
  }

}
