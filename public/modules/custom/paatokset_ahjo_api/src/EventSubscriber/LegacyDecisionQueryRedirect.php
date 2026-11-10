<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\EventSubscriber;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Routing\LocalRedirectResponse;
use Drupal\node\NodeInterface;
use Drupal\paatokset_ahjo_api\Entity\CaseBundle;
use Drupal\paatokset_ahjo_api\Service\CaseService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Redirects legacy case URLs with decision query parameter to decisions.
 *
 * Case pages used to display the selected decision with a query parameter,
 * e.g. /fi/asia/hel-2022-008715?paatos=1c7d448b-945e-44d0-9d91-bc9ae2c89fe5.
 * Decisions now have their own canonical URLs in all languages.
 */
final class LegacyDecisionQueryRedirect implements EventSubscriberInterface {

  /**
   * Legacy query parameters.
   */
  private const array QUERY_KEYS = ['paatos', 'beslut', 'decision'];

  public function __construct(
    private readonly CaseService $caseService,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Run after RouterListener (32) and before the dynamic page cache (27).
    return [
      KernelEvents::REQUEST => ['onRequest', 30],
    ];
  }

  /**
   * Redirects legacy case URLs.
   */
  public function onRequest(RequestEvent $event): void {
    $request = $event->getRequest();

    if (!$event->isMainRequest() || $request->attributes->get('_route') !== 'entity.node.canonical') {
      return;
    }

    $case = $request->attributes->get('node');
    if (!$case instanceof CaseBundle) {
      return;
    }

    $decisionId = NULL;
    $queryKey = NULL;
    foreach (self::QUERY_KEYS as $queryKey) {
      $value = $request->query->get($queryKey);
      if (is_string($value) && $value !== '') {
        $decisionId = $value;
        break;
      }
    }

    if (!$decisionId) {
      return;
    }

    $diaryNumber = $case->getDiaryNumber();
    $decision = $this->caseService->getDecision($decisionId, $diaryNumber)
      ?? $this->caseService->getDecisionFromRedirect($diaryNumber, $decisionId);

    // Display the case page if the decision is not found.
    if (!$decision instanceof NodeInterface) {
      return;
    }

    $url = $this->caseService->getDecisionUrlFromNode($decision);
    if (!$url) {
      return;
    }

    $generatedUrl = $url->toString(TRUE);

    $response = new LocalRedirectResponse($generatedUrl->getGeneratedUrl(), 301);
    $response->addCacheableDependency($generatedUrl);
    $response->addCacheableDependency($case);
    $response->addCacheableDependency($decision);
    $response->addCacheableDependency(
      (new CacheableMetadata())->addCacheContexts([
        'languages:language_interface',
        "url.query_args:$queryKey",
      ])
    );

    $event->setResponse($response);
  }

}
