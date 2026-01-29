<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\AhjoProxy\Routing;

use Drupal\paatokset_ahjo_api\AhjoProxy\Controller\AhjoProxyController;
use Symfony\Component\Routing\Route;

/**
 * The route provider for Ahjo proxy.
 */
final class RouteProvider {

  /**
   * Available endpoints.
   */
  private const array ENDPOINTS = [
    'cases' => '/cases',
    'case_single' => '/cases/{case}',
    'trustees' => '/agents/positionoftrust',
    'trustee' => '/agents/positionoftrust/{trustee}',
    'organizations' => '/organization',
    // Guess, not sure if `organization` endpoint exists:
    'organization' => '/organization/{organization}',
    'decisionmakingorganizations' => '/organization/decisionmakingorganizations',
    // Guess, not sure if `decisionmakingorganization` endpoint exists:
    'decisionmakingorganization' => '/organization/decisionmakingorganizations/{decisionmaker}',
    'decisionmakers' => '/agents/decisionmakers',
    // Guess, not sure if `decisionmaker` endpoint exists:
    'decisionmaker' => '/agents/decisionmakers/{decisionmaker}',
    'record' => '/records/{record}',
    'decisions' => '/decisions',
    'decision' => '/decisions/{decision}',
    'meetings' => '/meetings',
    'meeting' => '/meetings/{meeting}',
    'agenda_item' => '/meetings/{meeting}/agendaitems/{item}',
  ];

  /**
   * Ahjo proxy URL prefix.
   */
  private const string PREFIX = '/ahjo-proxy/v2';

  /**
   * Provides Ahjo proxy routes.
   *
   * @return \Symfony\Component\Routing\Route[]
   *   The route collection.
   */
  public function routes(): array {
    $routes = [];
    foreach (self::ENDPOINTS as $name => $endpoint) {
      $path = sprintf('%s%s', self::PREFIX, $endpoint);
      $routeName = sprintf('paatokset_ahjo_api.ahjo_proxy.%s', $name);

      $routes[$routeName] = new Route(
        path: $path,
        defaults: [
          '_controller' => AhjoProxyController::class . '::proxyRequest',
          'prefix' => self::PREFIX,
        ],
        requirements: [
          '_permission' => 'access ahjo proxy',
        ],
        options: [
          '_auth' => ['cookie', 'key_auth'],
        ],
        methods: ['GET'],
      );
    }
    return $routes;
  }

}
