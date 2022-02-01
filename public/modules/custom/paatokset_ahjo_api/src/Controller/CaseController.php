<?php

namespace Drupal\paatokset_ahjo_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for Case ajax events.
 */
class CaseController extends ControllerBase {

  /**
   * CaseService for getting case and decision data.
   *
   * @var \Drupal\paatokset_ahjo_api\Service\CaseService
   */
  private $caseService;

  /**
   * Class constructor.
   */
  public function __construct() {
    // Include twig engine.
    include_once \Drupal::root() . '/core/themes/engines/twig/twig.engine';
    $this->caseService = \Drupal::service('paatokset_ahjo_cases');
  }

  /**
   * Load a decision and return data as REST response.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   JSON object containing data
   */
  public function loadDecision(string $case_id) : Response {
    $decision_id = \Drupal::request()->query->get('decision');
    $this->caseService->setEntitiesById($case_id, $decision_id);

    $data['decision_pdf'] = $this->caseService->getDecisionPdf();
    $data['selectedDecision'] = $this->caseService->getSelectedDecision();
    $data['selected_class'] = $this->caseService->getDecisionClass();
    $data['attachments'] = $this->caseService->getAttachments();
    $data['next_decision'] = $this->caseService->getNextDecision();
    $data['previous_decision'] = $this->caseService->getPrevDecision();

    $content = twig_render_template(
      drupal_get_path('theme', 'helfi_paatokset') . '/templates/components/decision-content.html.twig',
      [
        'selectedDecision' => $data['selectedDecision'],
        'selectedClass' => $data['selectedClass'],
      ]
    );

    $attachments = twig_render_template(
      drupal_get_path('theme', 'helfi_paatokset') . '/templates/components/case-attachments.html.twig',
      [
        'attachments' => $data['attachments'],
      ]
    );

    $decision_navigation = twig_render_template(
      drupal_get_path('theme', 'helfi_paatokset') . '/templates/components/decision-navigation.html.twig',
      [
        'next_decision' => $data['next_decision'],
        'previous_decision' => $data['previous_decision'],
      ]
    );

    return new Response(json_encode([
      'content' => $content,
      'attachments' => $attachments,
      'decision_navigation' => $decision_navigation,
      'show_warning' => !empty($data['next_decision']),
      'decision_pdf' => $data['decision_pdf'],
    ]));
  }

}
