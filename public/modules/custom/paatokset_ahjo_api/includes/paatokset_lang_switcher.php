<?php

/**
 * @file
 * Hooks for Päätökset language switcher.
 */

declare(strict_types=1);

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\paatokset_ahjo_api\Entity\CaseBundle;
use Drupal\paatokset_ahjo_api\Entity\Decision;

/**
 * Implements hook_language_switch_links_alter().
 */
function paatokset_ahjo_api_language_switch_links_alter(array &$links, $type, Url $url): void {
  $currentLanguage = \Drupal::languageManager()->getCurrentLanguage()->getId();
  $route = \Drupal::routeMatch();
  $route_name = $route->getRouteName();

  // Don't act on NULL routes.
  if ($route_name === NULL) {
    return;
  }

  $node = NULL;
  if ($route_name === 'entity.node.canonical') {
    $node = $route->getParameter('node');
  }

  if (!_paatokset_lang_switcher_route_is_allowed($route_name)) {
    return;
  }

  foreach ($links as $langCode => $link) {
    // Don't do anything for current page.
    if ($langCode === $currentLanguage) {
      continue;
    }

    // Only act on untranslated node links.
    if ($node instanceof NodeInterface && isset($link['#untranslated']) && $link['#untranslated']) {
      $url = _paatokset_lang_switcher_get_alt_urls_for_node($node, $langCode);
    }
    elseif ($route_name !== 'entity.node.canonical') {
      $url = _paatokset_lang_switcher_get_alt_urls_for_routes($route, $langCode, $currentLanguage);

      // Set link as untranslated regardless if we get the localized URL.
      $links[$langCode]['#untranslated'] = TRUE;
    }

    if ($url instanceof Url) {
      try {
        $url->setOption('language', $link['language']);
        $links[$langCode]['#override_url'] = $url->toString();
        $links[$langCode]['#lang_override'] = TRUE;
      }
      // Catch exceptions just in case.
      catch (\Exception $e) {
        $links[$langCode]['#lang_override'] = FALSE;
      }
    }
  }
}

/**
 * Get alternate localized URLs for untranslated nodes.
 *
 * @param \Drupal\node\NodeInterface $node
 *   Current node.
 * @param string $langCode
 *   Langcode to get localized URL for.
 *
 * @return \Drupal\Core\Url|null
 *   Localized URL, if found.
 */
function _paatokset_lang_switcher_get_alt_urls_for_node(NodeInterface $node, string $langCode): ?Url {
  if ($node->bundle() === 'policymaker') {
    /** @var \Drupal\paatokset_policymakers\Service\PolicymakerService $policymakerService */
    $policymakerService = \Drupal::service('paatokset_policymakers');
    return $policymakerService->getPolicymakerRoute($node, $langCode);
  }
  elseif ($node instanceof CaseBundle) {
    static $decisions = [];

    /** @var \Drupal\paatokset_ahjo_api\Service\CaseService $caseService */
    $caseService = \Drupal::service('paatokset_ahjo_cases');

    // Optimization: cache guess results. This should avoid few database
    // queries when this function is called for each language.
    if (!($decision = $decisions[$node->id()] ?? NULL)) {
      $decisions[$node->id()] = $decision = $caseService->guessDecisionFromPath($node);
    }

    return $caseService->getCaseUrlFromNode($node->getDiaryNumber(), $decision, $langCode);
  }
  elseif ($node->bundle() === 'decision') {
    /** @var \Drupal\paatokset_ahjo_api\Service\CaseService $caseService */
    $caseService = \Drupal::service('paatokset_ahjo_cases');
    return $caseService->getDecisionUrlFromNode($node, $langCode);
  }
  elseif ($node->bundle() === 'trustee') {
    /** @var \Drupal\paatokset_policymakers\Service\PolicymakerService $policymakerService */
    $policymakerService = \Drupal::service('paatokset_policymakers');
    return $policymakerService->getTrusteeUrl($node, $langCode);
  }

  return NULL;
}

/**
 * Get alternative localized URLs based on routes.
 *
 * @param \Drupal\Core\Routing\RouteMatchInterface $route
 *   Route to get localized URL from.
 * @param string $langCode
 *   Langcode to get localized URL for.
 * @param string $currentLanguage
 *   Current language.
 *
 * @return \Drupal\Core\Url|null
 *   Localized URL, if found.
 */
function _paatokset_lang_switcher_get_alt_urls_for_routes(RouteMatchInterface $route, string $langCode, string $currentLanguage): ?Url {
  static $decisions = [];

  $route_name = $route->getRouteName();

  /** @var \Drupal\paatokset_policymakers\Service\PolicymakerService $policymakerService */
  $policymakerService = \Drupal::service('paatokset_policymakers');

  /** @var \Drupal\paatokset_ahjo_api\Service\CaseService $caseService */
  $caseService = \Drupal::service('paatokset_ahjo_cases');

  // Special case for getting case URLs.
  // This is because we might want to get translated decision IDs.
  if (str_starts_with($route_name, 'paatokset_case.')) {
    // Case route item can be case or decision node.
    // See paatokest_ahjo_api.routing.yml.
    /** @var \Drupal\paatokset_ahjo_api\Entity\CaseBundle|\Drupal\paatokset_ahjo_api\Entity\Decision $caseOrDecision */
    $caseOrDecision = $route->getParameter('case');

    $diaryNumber = $caseOrDecision->get('field_diary_number')->value;

    // Optimization: Cache guessing so we save on DB queries. This
    // function will be called multiple time for different languages.
    if (!($decision = $decisions[$caseOrDecision->id()] ?? NULL)) {
      $decision = NULL;

      if ($caseOrDecision instanceof Decision) {
        $decision = $caseOrDecision;
      }
      elseif ($caseOrDecision instanceof CaseBundle) {
        $decision = $caseService->guessDecisionFromPath($caseOrDecision);
      }

      // Save for later.
      $decisions[$caseOrDecision->id()] = $decision;
    }

    // Some decisions do not have diary number (nor case).
    // For example, "pöytäkirjan tarkastajien valinta".
    // getDecisionUrlFromNode() has fallback logic for this case.
    if (!$diaryNumber) {
      return $caseService->getDecisionUrlFromNode($decision, $langCode);
    }

    return $caseService->getCaseUrlFromNode($diaryNumber, $decision, $langCode)
      ?: $caseService->getDecisionUrlFromNode(NULL, $langCode);
  }

  $localized_route = str_replace('.' . $currentLanguage, '.' . $langCode, $route_name);

  // Quit early if route doesn't exist.
  if (!$policymakerService->routeExists($localized_route)) {
    return NULL;
  }

  // Get parameters from current route.
  $parameters = [];
  foreach ($route->getParameters() as $key => $value) {
    // Special case for translating policymaker organization parameter.
    if ($key === 'organization') {
      // Policymaker gets lost when returning page from cache, so set it again.
      $policymakerService->setPolicyMakerByPath();
      $organization = $policymakerService->getPolicyMaker()?->getPolicymakerOrganizationFromUrl($langCode);

      if ($organization) {
        $parameters[$key] = $organization;
      }
      // Fallback for trustee nodes.
      else {
        $parameters[$key] = $value;
      }
    }
    elseif ($key === 'decision') {
      $nativeId = $caseService->normalizeNativeId($value->get('field_decision_native_id')->getString());
      $parameters[$key] = $nativeId;
    }
    else {
      $parameters[$key] = $value;
    }
  }

  return Url::fromRoute($localized_route, $parameters);
}

/**
 * Check if route is allowed to be altered.
 *
 * @param string $route_name
 *   Route name to check.
 *
 * @return bool
 *   If route can be altered (internal Päätökset routes).
 */
function _paatokset_lang_switcher_route_is_allowed(string $route_name): bool {
  // Always allow node routes to be altered.
  if ($route_name === 'entity.node.canonical') {
    return TRUE;
  }

  // Check custom route patterns.
  $partial_routes = [
    'policymakers.',
    'policymaker.',
    'paatokset_decision.',
    'paatokset_case.',
    'paatokset_search.decisions',
  ];

  foreach ($partial_routes as $allowed_route) {
    if (str_starts_with($route_name, $allowed_route)) {
      return TRUE;
    }
  }

  // Disallow all other routes.
  return FALSE;
}
