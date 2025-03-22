<?php

namespace Drupal\paatokset_ahjo_api\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Url;
use Drupal\paatokset_ahjo_api\Entity\Decision;
use Drupal\paatokset_ahjo_api\Service\CaseService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for Case ajax events.
 */
final class CaseController extends ControllerBase {

  /**
   * Class constructor.
   *
   * @param \Drupal\paatokset_ahjo_api\Service\CaseService $caseService
   *   CaseService for getting case and decision data.
   * @param \Drupal\Core\Extension\ThemeExtensionList $extensionList
   *   Theme extension list.
   */
  public function __construct(
    private readonly CaseService $caseService,
    private readonly ThemeExtensionList $extensionList,
  ) {
    // Include twig engine.
    // phpcs:ignore
    include_once \Drupal::root() . '/core/themes/engines/twig/twig.engine';
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('paatokset_ahjo_cases'),
      $container->get('extension.list.theme'),
    );
  }

  /**
   * Load a decision and return data as REST response.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   JSON object containing data
   */
  public function loadDecision(Decision $decision): Response {
    $this->caseService->setEntitiesFromDecision($decision);

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

    $content = $this->renderTemplate('/templates/components/decision-content.html.twig', [
      'selectedDecision' => $decision,
      'policymaker_is_active' => $data['policymaker_is_active'],
      'selected_class' => $data['selected_class'],
      'decision_org_name' => $data['decision_org_name'],
      'decision_content' => $data['decision_content'],
      'decision_section' => $decision->getFormattedDecisionSection(),
      'vote_results' => $decision->getVotingResults(),
    ]);

    $attachments = $this->renderTemplate('/templates/components/case-attachments.html.twig', [
      'attachments' => $decision->getAttachments(),
    ]);

    $decision_navigation = $this->renderTemplate('/templates/components/decision-navigation.html.twig', [
      'next_decision' => $data['next_decision'],
      'previous_decision' => $data['previous_decision'],
    ]);

    $response = new CacheableJsonResponse([
      'content' => $content,
      'language_urls' => $language_urls,
      'attachments' => $attachments,
      'decision_navigation' => $decision_navigation,
      'show_warning' => !empty($data['next_decision']),
      'decision_pdf' => $data['decision_pdf'],
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
   * Render twig template in hdbt_subtheme folder.
   */
  private function renderTemplate(string $path, array $variables): string {
    $template_file = $this->extensionList->getPath('hdbt_subtheme') . $path;

    return twig_render_template($template_file, $variables);
  }

}
