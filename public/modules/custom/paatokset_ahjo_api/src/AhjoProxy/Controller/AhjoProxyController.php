<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\AhjoProxy\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\paatokset_ahjo_api\AhjoOpenId\AhjoOpenId;
use GuzzleHttp\ClientInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ahjo proxy v2 controller.
 *
 * Ahjo API is behind a firewall, so this proxy can be used to access
 * the API from environments that do not have direct access to Ahjo,
 * such as local development.
 *
 * This controller aims to be simpler and lighter weight that v1 implementation.
 * Ahjo URLs and query parameters are not transformed in any way, so requests
 * can be passed as-is to Ahjo API.
 *
 * The Ahjo responses are returned as-is.
 *
 * @see \Drupal\paatokset_ahjo_proxy\Controller\AhjoProxyController
 * @see \Drupal\paatokset_ahjo_api\AhjoProxy\Routing\RouteProvider
 */
final class AhjoProxyController extends ControllerBase {

  use AutowireTrait;

  public function __construct(
    private readonly ClientInterface $client,
    private readonly AhjoOpenId $token,
  ) {
  }

  /**
   * Proxies a request to Ahjo API.
   */
  public function proxyRequest(Request $request, string $prefix): Response {
    $path = $request->getPathInfo();

    // Remove everything up to and including $prefix.
    $proxyPath = preg_replace('#^.*/' . preg_quote(ltrim($prefix, '/'), '#') . '#', '', $path);

    $config = $this->config('paatokset_ahjo_api.settings');
    $base = $config->get('ahjo_endpoint');
    if (!$base) {
      return new JsonResponse(['error' => 'Ahjo endpoint not configured.'], 500);
    }

    // Include query string if present.
    $queryString = $request->getQueryString();
    $url = $base . $proxyPath . ($queryString ? '?' . $queryString : '');

    $response = $this->client->request('GET', $url, [
      'query' => $request->query->all(),
      'http_errors' => FALSE,
      'headers' => [
        'Authorization' => 'Bearer ' . $this->token->getAuthToken(),
      ],
    ]);

    return new Response(
      (string) $response->getBody(),
      $response->getStatusCode(),
      [
        'Content-Type' => $response->getHeaderLine('Content-Type'),
      ]
    );
  }

}
