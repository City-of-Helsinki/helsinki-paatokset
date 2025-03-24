<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Controller;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\paatokset_ahjo_api\Entity\Decision;
use Drupal\paatokset_ahjo_api\Entity\Policymaker;
use Drupal\paatokset_ahjo_api\Service\CaseService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for Case ajax events.
 */
final class CaseController extends ControllerBase {

  use AutowireTrait;

  /**
   * Class constructor.
   *
   * @param \Drupal\paatokset_ahjo_api\Service\CaseService $caseService
   *   CaseService for getting case and decision data.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(
    private readonly CaseService $caseService,
    private readonly RendererInterface $renderer,
  ) {
  }

  /**
   * Load a decision and return data as REST response.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   JSON object containing data
   */
  public function loadDecision(Decision $decision): Response {
    $this->caseService->setEntitiesFromDecision($decision);
    $policymaker = $decision->getPolicyMaker();

    $data = [];
    _paatokset_ahjo_api_get_decision_variables($data, $this->caseService);

    $all_decisions_link = $this->caseService->getDecisionMeetingLink();
    if ($all_decisions_link instanceof Url) {
      $all_decisions_link = $all_decisions_link->toString();
    }

    $other_decisions_link = $this->caseService->getPolicymakerDecisionsLink();
    if ($other_decisions_link instanceof Url) {
      $other_decisions_link = $other_decisions_link->toString();
    }

    $languages = $this->languageManager()->getLanguages();
    $language_urls = [];
    foreach ($languages as $langcode => $language) {
      $lang_url = $this->caseService->getCaseUrlFromNode(NULL, $langcode);
      if ($lang_url) {
        $lang_url->setOption('language', $language);
        $language_urls[$langcode] = $lang_url->toString();
      }
    }

    $content = $this->renderDecisionContent($decision, $policymaker, $data);
    $attachments = $this->renderCaseAttachments($decision);
    $navigation = [
      '#theme' => 'decision_navigation',
      '#next_decision' => $this->caseService->getNextDecision($decision),
      '#previous_decision' => $this->caseService->getPrevDecision($decision),
    ];

    $response = new CacheableJsonResponse([
      'content' => $content,
      'language_urls' => $language_urls,
      'attachments' => $attachments,
      'decision_navigation' => $this->renderer->renderInIsolation($navigation),
      'show_warning' => !empty($navigation['#next_decision']),
      'decision_pdf' => $decision->getDecisionPdf(),
      'all_decisions_link' => $all_decisions_link,
      'other_decisions_link' => $other_decisions_link,
    ]);

    $response->addCacheableDependency($decision);
    if ($this->caseService->getSelectedCase()) {
      $response->addCacheableDependency($this->caseService->getSelectedCase());
    }

    return $response;
  }

  /**
   * Renders decision content.
   */
  private function renderDecisionContent(Decision $decision, ?Policymaker $policymaker, array $data): MarkupInterface {
    $build = [
      '#theme' => 'decision_content',
      '#selectedDecision' => $decision,
      '#policymaker_is_active' => $policymaker?->isActive() ?? FALSE,
      '#selected_class' => Html::cleanCssIdentifier($policymaker?->getPolicymakerClass() ?? 'color-sumu'),
      '#decision_org_name' => $data['decision_org_name'],
      '#decision_content' => $decision->parseContent(),
      '#decision_section' => $decision->getFormattedDecisionSection(),
      '#vote_results' => $decision->getVotingResults(),
    ];
    return $this->renderer->renderInIsolation($build);
  }

  /**
   * Renders case attachments.
   */
  private function renderCaseAttachments(Decision $decision): MarkupInterface {
    $build = [
      '#theme' => 'case_attachments',
      '#attachments' => $decision->getAttachments(),
    ];
    return $this->renderer->renderInIsolation($build);
  }

}
