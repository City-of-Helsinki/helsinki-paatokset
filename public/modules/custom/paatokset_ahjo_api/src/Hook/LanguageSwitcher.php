<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\OrderAfter;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\paatokset_ahjo_api\Entity\CaseBundle;
use Drupal\paatokset_ahjo_api\Entity\Decision;
use Drupal\paatokset_ahjo_api\Service\CaseService;
use Drupal\paatokset_ahjo_api\Service\PolicymakerService;

/**
 * Language switcher hooks.
 */
readonly class LanguageSwitcher {

  public function __construct(
    private LanguageManagerInterface $languageManager,
    private RouteMatchInterface $routeMatch,
    private CaseService $caseService,
    private PolicymakerService $policyMakerService,
  ) {
  }

  /**
   * Implements hook_language_switch_links_alter().
   *
   * Most decisions and cases are not translated. We still want to
   * serve the untranslated content with translated UI elements. This
   * hooks forces language switcher to display links to the content in
   * all languages.
   */
  #[Hook('language_switch_links_alter', order: new OrderAfter(['hdbt_admin_tools']))]
  public function languageSwitchLinksAlter(array &$links, $type, Url $url): void {
    $route_name = $this->routeMatch->getRouteName();

    if (!$this->isRouteAllowed($route_name)) {
      return;
    }

    $currentLanguage = $this->languageManager
      ->getCurrentLanguage()
      ->getId();

    $node = match ($route_name) {
      'entity.node.canonical' => $this->routeMatch->getParameter('node'),
      default => NULL,
    };

    foreach ($links as $langCode => $link) {
      // Don't do anything for the current language.
      if ($langCode === $currentLanguage) {
        continue;
      }

      if ($route_name === 'entity.ahjo_case.canonical') {
        $url = $link['url'];
      }
      // This relies on the `#untranslated` key set by
      // hdbt_admin_tools. The hooks from hdbt_admin_tools must be run before
      // this code is run.
      elseif ($node instanceof NodeInterface && isset($link['#untranslated']) && $link['#untranslated']) {
        $url = $this->getAltUrlsForNode($node, $langCode);
      }
      elseif ($route_name !== 'entity.node.canonical') {
        $url = $this->getAltUrlsForRoutes($langCode, $currentLanguage);

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
  private function getAltUrlsForNode(NodeInterface $node, string $langCode): ?Url {
    if ($node->bundle() === 'policymaker') {
      return $this->policyMakerService->getPolicymakerRoute($node, $langCode);
    }
    elseif ($node instanceof CaseBundle) {
      static $decisions = [];

      // Optimization: cache guess results. This should avoid few database
      // queries when this function is called for each language.
      if (!($decision = $decisions[$node->id()] ?? NULL)) {
        $decisions[$node->id()] = $decision = $this->caseService->guessDecisionFromPath($node);
      }

      return $this->caseService->getCaseUrlFromNode($node->getDiaryNumber(), $decision, $langCode);
    }
    elseif ($node->bundle() === 'decision') {
      return $this->caseService->getDecisionUrlFromNode($node, $langCode);
    }
    elseif ($node->bundle() === 'trustee') {
      return $this->policyMakerService->getTrusteeUrl($node, $langCode);
    }

    return NULL;
  }

  /**
   * Get alternative localized URLs based on routes.
   *
   * @param string $langCode
   *   Langcode to get localized URL for.
   * @param string $currentLanguage
   *   Current language.
   *
   * @return \Drupal\Core\Url|null
   *   Localized URL, if found.
   */
  private function getAltUrlsForRoutes(string $langCode, string $currentLanguage): ?Url {
    static $decisions = [];

    $route_name = $this->routeMatch->getRouteName();

    // Special case for getting case URLs.
    // This is because we might want to get translated decision IDs.
    if (str_starts_with($route_name, 'paatokset_case.')) {
      // Case route item can be case or decision node.
      // See paatokest_ahjo_api.routing.yml.
      /** @var \Drupal\paatokset_ahjo_api\Entity\CaseBundle|\Drupal\paatokset_ahjo_api\Entity\Decision $caseOrDecision */
      $caseOrDecision = $this->routeMatch->getParameter('case');

      $diaryNumber = $caseOrDecision->get('field_diary_number')->value;

      // Optimization: Cache guessing so we save on DB queries. This
      // function will be called multiple time for different languages.
      if (!($decision = $decisions[$caseOrDecision->id()] ?? NULL)) {
        $decision = NULL;

        if ($caseOrDecision instanceof Decision) {
          $decision = $caseOrDecision;
        }
        elseif ($caseOrDecision instanceof CaseBundle) {
          $decision = $this->caseService->guessDecisionFromPath($caseOrDecision);
        }

        // Save for later.
        $decisions[$caseOrDecision->id()] = $decision;
      }

      // Some decisions do not have diary number (nor case).
      // For example, "pöytäkirjan tarkastajien valinta".
      // getDecisionUrlFromNode() has fallback logic for this case.
      if (!$diaryNumber) {
        return $this->caseService->getDecisionUrlFromNode($decision, $langCode);
      }

      return $this->caseService->getCaseUrlFromNode($diaryNumber, $decision, $langCode)
        ?: $this->caseService->getDecisionUrlFromNode(NULL, $langCode);
    }

    $localized_route = str_replace('.' . $currentLanguage, '.' . $langCode, $route_name);

    // Quit early if route doesn't exist.
    if (!$this->policyMakerService->routeExists($localized_route)) {
      return NULL;
    }

    // Get parameters from current route.
    $parameters = [];
    foreach ($this->routeMatch->getParameters() as $key => $value) {
      // Special case for translating policymaker organization parameter.
      if ($key === 'organization') {
        // Policymaker gets lost when returning page from cache.
        $this->policyMakerService->setPolicyMakerByPath();
        $organization = $this->policyMakerService
          ->getPolicyMaker()
          ?->getPolicymakerOrganizationFromUrl($langCode);

        if ($organization) {
          $parameters[$key] = $organization;
        }
        // Fallback for trustee nodes.
        else {
          $parameters[$key] = $value;
        }
      }
      elseif ($key === 'decision') {
        assert($value instanceof Decision);
        $nativeId = $value->getNormalizedNativeId();
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
  private function isRouteAllowed(string $route_name): bool {
    $routes = [
      'entity.node.canonical',
      'entity.ahjo_case.canonical',
    ];

    if (in_array($route_name, $routes)) {
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

    // Disallow all other routes.
    return array_any($partial_routes, fn($allowed_route) => str_starts_with($route_name, $allowed_route));
  }

}
