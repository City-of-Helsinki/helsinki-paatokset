<?php

namespace Drupal\paatokset_ahjo\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\paatokset_ahjo\Service\IssueService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller.
 */
class IssueController extends ControllerBase {

  /**
   * IssueService for getting Issue data.
   *
   * @var issueService
   */
  private $issueService;

  /**
   * Class constructor.
   */
  public function __construct() {
    // Include twig engine.
    include_once \Drupal::root() . '/core/themes/engines/twig/twig.engine';
    $this->issueService = \Drupal::getContainer()->get(IssueService::class);
  }

  /**
   * Load a decision and return data as REST response.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   JSON object containing data
   */
  public function loadDecision() : Response {
    $data = $this->issueService->getData();
    $data['diarynumber'] = $this->issueService->getDiaryNumber();

    $content = twig_render_template(
          drupal_get_path('theme', 'hdbt_subtheme') . '/templates/content/paatokset-issue--content.html.twig',
          $data
      );

    $attachments = twig_render_template(
          drupal_get_path('theme', 'hdbt_subtheme') . '/templates/content/paatokset-issue--attachments.html.twig',
          [
            'attachments' => $data['attachments'],
            'confidentiality_reasons' => $data['confidentiality_reasons'],
          ]
      );

    $decision_navigation = twig_render_template(
          drupal_get_path('theme', 'hdbt_subtheme') . '/templates/content/paatokset-issue--decision-navigation.html.twig',
          [
            'previous_handling' => $data['previous_handling'],
            'next_handling' => $data['next_handling'],
          ]
      );

    return new Response(
          json_encode(
              [
                'content' => $content,
                'attachments' => $attachments,
                'decision_navigation' => $decision_navigation,
                'show_warning' => $data['next_handling'],
                'all_decisions_link' => $data['all_decisions_link'],
              ]
          )
      );
  }

}
