<?php

declare(strict_types=1);

namespace App\Controller;

use App\AhjoOpenId\AhjoOpenId;
use App\Auth\RequireApiKey;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Ahjo proxy controller.
 *
 * Ahjo API is behind a firewall, so this proxy can be used to access
 * the API from environments that do not have direct access to Ahjo,
 * such as local development.
 *
 * Ahjo URLs and query parameters are not transformed in any way, so
 * requests can be passed as-is to Ahjo API. The Ahjo responses are
 * returned as-is.
 *
 * If an upstream proxy is configured, requests are forwarded to it
 * instead of Ahjo. This simplifies Drupal configuration. Each Drupal
 * only talks to its own ahjo proxy, and if Ahjo configuration
 * is missing, requests are be forwarded to production.
 */
#[RequireApiKey]
final readonly class ProxyController {

  /**
   * Route requirement for path parameters.
   *
   * Path parameters cannot contain slashes or be relative path segments.
   */
  private const string PARAM = '(?!\.\.?$)[^/]+';

  public function __construct(
    private HttpClientInterface $httpClient,
    private AhjoOpenId $ahjoOpenId,
    private LoggerInterface $logger,
    private string $ahjoEndpoint,
    private string $upstreamProxyUrl,
    private string $upstreamProxyApiKey,
  ) {
  }

  /**
   * Proxies a request to Ahjo API.
   */
  #[Route('/cases', name: 'proxy_cases', methods: ['GET'])]
  #[Route('/cases/{case}', name: 'proxy_case', requirements: ['case' => self::PARAM], methods: ['GET'])]
  #[Route('/cases/{case}/handlings/{handling}', name: 'proxy_case_handling', requirements: [
    'case' => self::PARAM,
    'handling' => self::PARAM,
  ], methods: ['GET'])]
  #[Route('/cases/{case}/records', name: 'proxy_case_records', requirements: ['case' => self::PARAM], methods: ['GET'])]
  #[Route('/decisions', name: 'proxy_decisions', methods: ['GET'])]
  #[Route('/decisions/{decision}', name: 'proxy_decision', requirements: ['decision' => self::PARAM], methods: ['GET'])]
  #[Route('/resolutions', name: 'proxy_resolutions', methods: ['GET'])]
  #[Route('/initiatives', name: 'proxy_initiatives', methods: ['GET'])]
  #[Route('/records/{record}', name: 'proxy_record', requirements: ['record' => self::PARAM], methods: ['GET'])]
  #[Route('/sectors', name: 'proxy_sectors', methods: ['GET'])]
  #[Route('/functions', name: 'proxy_functions', methods: ['GET'])]
  #[Route('/meetings', name: 'proxy_meetings', methods: ['GET'])]
  #[Route('/meetings/{meeting}', name: 'proxy_meeting', requirements: ['meeting' => self::PARAM], methods: ['GET'])]
  #[Route('/meetings/{meeting}/agendaitems/{item}', name: 'proxy_agenda_item', requirements: [
    'meeting' => self::PARAM,
    'item' => self::PARAM,
  ], methods: ['GET'])]
  #[Route('/motions', name: 'proxy_motions', methods: ['GET'])]
  #[Route('/organization', name: 'proxy_organization', methods: ['GET'])]
  #[Route('/organization/decisionmakingorganizations', name: 'proxy_decisionmakingorganizations', methods: ['GET'])]
  #[Route('/agents/positionoftrust', name: 'proxy_trustees', methods: ['GET'])]
  #[Route('/agents/positionoftrust/{trustee}', name: 'proxy_trustee', requirements: ['trustee' => self::PARAM], methods: ['GET'])]
  #[Route('/councilgroups', name: 'proxy_councilgroups', methods: ['GET'])]
  #[Route('/agents/decisionmakers', name: 'proxy_decisionmakers', methods: ['GET'])]
  public function proxyRequest(Request $request): Response {
    if ($this->upstreamProxyUrl !== '') {
      $baseUrl = $this->upstreamProxyUrl;
      $headers = ['api-key' => $this->upstreamProxyApiKey];
    }
    else {
      $token = $this->ahjoOpenId->getAuthToken();
      if (!$token) {
        return new JsonResponse(['error' => 'Ahjo auth token is not available.'], Response::HTTP_SERVICE_UNAVAILABLE);
      }

      $baseUrl = $this->ahjoEndpoint;
      $headers = ['Authorization' => 'Bearer ' . $token];
    }

    // Pass the path and the query string as-is, except for the api key.
    $queryString = self::removeApiKey($request->server->getString('QUERY_STRING'));
    $url = rtrim($baseUrl, '/') . $request->getPathInfo() . ($queryString !== '' ? '?' . $queryString : '');

    try {
      $response = $this->httpClient->request('GET', $url, [
        'headers' => $headers,
      ]);

      // Pass HTTP errors from Ahjo through as-is.
      $content = $response->getContent(FALSE);
      $headers = $response->getHeaders(FALSE);

      return new Response($content, $response->getStatusCode(), [
        'Content-Type' => $headers['content-type'][0] ?? 'application/json',
      ]);
    }
    catch (ExceptionInterface $e) {
      $this->logger->error('Ahjo request failed: {message}', [
        'message' => $e->getMessage(),
        'path' => $request->getPathInfo(),
      ]);

      return new JsonResponse(['error' => 'Ahjo request failed.'], Response::HTTP_BAD_GATEWAY);
    }
  }

  /**
   * Removes the api key from a raw query string.
   */
  private static function removeApiKey(string $queryString): string {
    $parts = array_filter(
      explode('&', $queryString),
      static fn (string $part) => urldecode(explode('=', $part, 2)[0]) !== 'api-key',
    );

    return implode('&', $parts);
  }

}
