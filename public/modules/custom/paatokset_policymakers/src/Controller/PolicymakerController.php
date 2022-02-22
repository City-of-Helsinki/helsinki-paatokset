<?php

namespace Drupal\paatokset_policymakers\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Controller class for policymaker custom routes.
 */
class PolicymakerController extends ControllerBase {

  /**
   * Controller for policymaker subpages.
   */
  public function __construct() {
    $this->policymakerService = \Drupal::service('paatokset_policymakers');
    $this->policymakerService->setPolicyMakerByPath();
  }

  /**
   * Policymaker documents route.
   *
   * @return array
   *   Render array.
   */
  public function documents(): array {
    $policymaker = $this->policymakerService->getPolicymaker();

    if (!$policymaker instanceof NodeInterface) {
      return [];
    }

    $build = [
      '#title' => t('Documents: @title', ['@title' => $policymaker->get('title')->value]),
    ];

    if ($policymaker->get('field_documents_description')->value) {
      $build['#markup'] = '<div>' . $policymaker->get('field_documents_description')->value . '</div>';
    }

    return $build;
  }

  /**
   * Return title as translatable string.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   Documents title.
   */
  public static function getDocumentsTitle(): TranslatableMarkup {
    $policymakerService = \Drupal::service('paatokset_policymakers');
    $policymakerService->setPolicyMakerByPath();
    $policymaker = $policymakerService->getPolicymaker();

    if (!$policymaker instanceof NodeInterface) {
      return t('Documents');
    }

    return t('Documents: @name', ['@name' => $policymaker->get('title')->value]);
  }

  /**
   * Policymaker decisions route.
   *
   * @return array
   *   Render array.
   */
  public function decisions(): array {
    $policymaker = $this->policymakerService->getPolicymaker();

    if (!$policymaker instanceof NodeInterface) {
      return [];
    }

    $build = [
      '#title' => t('Decisions: @title', ['@title' => $this->policymakerService->getPolicymaker()->get('title')->value]),
    ];

    if ($policymaker->get('field_decisions_description')->value) {
      $build['description'] = [
        '#markup' => '<div>' . $policymaker->get('field_decisions_description')->value . '</div>',
      ];
    }

    return $build;
  }

  /**
   * Return title as translatable string.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   Decisions title.
   */
  public static function getDecisionsTitle(): TranslatableMarkup {
    $policymakerService = \Drupal::service('paatokset_policymakers');
    $policymakerService->setPolicyMakerByPath();
    $policymaker = $policymakerService->getPolicymaker();

    if (!$policymaker instanceof NodeInterface) {
      return t('Decisions');
    }

    return t('Decisions: @name', ['@name' => $policymaker->get('title')->value]);
  }

  /**
   * Policymaker dicussion minutes route.
   *
   * @return array
   *   Render array.
   */
  public function discussionMinutes(): array {
    $build = ['#title' => t('Discussion minutes: @title', ['@title' => $this->policymakerService->getPolicymaker()->get('title')->value])];
    return $build;
  }

  /**
   * Return view for singular minutes.
   *
   * @param string $id
   *   Meeting ID.
   *
   * @return array
   *   Render array.
   */
  public function minutes(string $id): array {
    $meetingData = $this->policymakerService->getMeetingAgenda($id);

    $build = [
      '#theme' => 'policymaker_minutes',
    ];

    if ($meetingData) {
      $build['meeting'] = $meetingData['meeting'];
      $build['list'] = $meetingData['list'];
      $build['file'] = $meetingData['file'];
    }

    $minutesOfDiscussion = $this->policymakerService->getMinutesOfDiscussion(1, FALSE, $id);
    if ($minutesOfDiscussion) {
      $build['minutes_of_discussion'] = $minutesOfDiscussion;
    }

    return $build;
  }

  /**
   * Return title as translatable string.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   Discussions title.
   */
  public static function getDiscussionMinutesTitle(): TranslatableMarkup {
    $policymakerService = \Drupal::service('paatokset_policymakers');
    $policymakerService->setPolicyMakerByPath();
    $policymaker = $policymakerService->getPolicymaker();

    if (!$policymaker instanceof NodeInterface) {
      return t('Discussion minutes');
    }

    return t('Discussion minutes: @name', ['@name' => $policymaker->get('title')->value]);
  }

  /**
   * Return translatable title for minutes.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string
   *   Minutes title.
   */
  public function getMinutesTitle($id) {
    $meetingData = $this->policymakerService->getMeetingAgenda($id);

    if (isset($meetingData['meeting']) && isset($meetingData['meeting']['title'])) {
      return $meetingData['meeting']['title'];
    }

    return t('Minutes');
  }

  /**
   * Get decision maker composition in JSON format.
   *
   * @param string $id
   *   Decision maker ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Return response with JSON data.
   */
  public function orgComposition(string $id): JsonResponse {
    $this->policymakerService->setPolicyMaker($id);
    $data = $this->policymakerService->getComposition();
    return new JsonResponse($data);
  }

}
