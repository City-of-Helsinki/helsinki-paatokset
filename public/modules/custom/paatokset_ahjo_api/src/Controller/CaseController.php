<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Controller;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Render\RendererInterface;
use Drupal\paatokset_ahjo_api\Entity\AhjoCase;
use Drupal\paatokset_ahjo_api\Entity\Decision;
use Drupal\paatokset_ahjo_api\Entity\Policymaker;
use Drupal\paatokset_ahjo_api\Service\CaseService;
use Drupal\paatokset_ahjo_api\Service\PolicymakerService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for Case ajax events.
 */
final class CaseController extends ControllerBase {

  use AutowireTrait;

  public function __construct(
    private readonly CaseService $caseService,
    private readonly RendererInterface $renderer,
    private readonly PolicymakerService $policymakerService,
  ) {
  }

  /**
   * Load a decision and return data as REST response.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   JSON object containing data
   */
  public function loadDecision(Decision $decision): Response {
    $languages = $this->languageManager()->getLanguages();
    $language_urls = [];
    foreach ($languages as $langcode => $language) {
      if ($lang_url = $this->caseService->getDecisionUrlFromNode($decision, $langcode)) {
        $language_urls[$langcode] = $lang_url->toString();
      }
    }

    $langcode = $this->languageManager()->getCurrentLanguage()->getId();
    $policymaker = $decision->getPolicyMaker($langcode);
    $case = $decision->getCase();

    $content = $this->renderDecisionContent($decision, $policymaker);
    $attachments = $this->renderCaseAttachments($decision);
    $next = $case?->getNextDecision($decision);
    $prev = $case?->getPrevDecision($decision);

    $navigation = [
      '#theme' => 'decision_navigation',
      '#next_decision' => $next,
      '#previous_decision' => $prev,
    ];

    $response = new CacheableJsonResponse([
      'url' => $decision->toUrl('canonical', ['language' => $this->languageManager()->getCurrentLanguage()])->toString(),
      'content' => $content,
      'language_urls' => $language_urls,
      'attachments' => $attachments,
      'decision_navigation' => $this->renderer->renderInIsolation($navigation),
      'show_warning' => !empty($navigation['#next_decision']),
      'decision_pdf' => $decision->getDecisionPdf(),
      'all_decisions_link' => $decision->getDecisionMeetingLink()?->toString(),
      'other_decisions_link' => $policymaker?->getDecisionsRoute($langcode)?->toString(),
    ]);

    $response->addCacheableDependency($decision);

    if ($policymaker) {
      $response->addCacheableDependency($policymaker);
    }

    return $response;
  }

  /**
   * Renders decision content.
   */
  private function renderDecisionContent(Decision $decision, Policymaker|NULL $policymaker): MarkupInterface|string {
    $build = [
      '#theme' => 'decision_content',
      '#selectedDecision' => $decision,
      '#policymaker_is_active' => $policymaker?->isActive() ?? FALSE,
      '#selected_class' => Html::cleanCssIdentifier($policymaker?->getPolicymakerClass() ?? 'color-board'),
      '#decision_org_name' => $policymaker?->getPolicymakerName() ?? $decision->getDecisionMakerOrgName(),
      '#organization_type_name' => $this->policymakerService->getPolicymakerTypeFromNode($policymaker) ?? NULL,
      '#decision_content' => $decision->parseContent(),
      '#decision_section' => $decision->getFormattedDecisionSection(),
      '#vote_results' => $decision->getVotingResults(),
    ];
    return $this->renderer->renderInIsolation($build);
  }

  /**
   * Renders case attachments.
   */
  private function renderCaseAttachments(Decision $decision): MarkupInterface|string {
    $build = [
      '#theme' => 'case_attachments',
      '#attachments' => $decision->getAttachments(),
    ];
    return $this->renderer->renderInIsolation($build);
  }

  /**
   * Case canonical route controller.
   *
   * Case are not independent. They should always be displayed with a decision.
   *
   * @return array
   *   A render array.
   */
  public function view(AhjoCase $ahjo_case): array {
    // Cases are not useful if they don't have a decision.
    if (!$ahjo_case->getDefaultDecision()) {
      throw new NotFoundHttpException("Case with ID '{$ahjo_case->id()}' does not have a decision.");
    }

    // Otherwise, render the entity normally.
    return $this
      ->entityTypeManager()
      ->getViewBuilder('ahjo_case')
      ->view($ahjo_case);
  }

}
