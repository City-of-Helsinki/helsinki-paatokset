<?php

namespace Drupal\paatokset_ahjo\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller class for policymaker custom routes.
 */
class PolicymakerController extends ControllerBase {

  /**
   * Controller for policymaker subpages.
   */
  public function __construct() {
    $this->policymakerService = \Drupal::service('Drupal\paatokset_ahjo\Service\PolicymakerService');
  }

  /**
   * Policymaker documents route.
   */
  public function documents($organization) {
    $policymaker = $this->policymakerService->getPolicymaker();

    $build = [
      '#title' => t('Documents: @title', ['@title' => $policymaker->get('title')->value]),
    ];

    if ($policymaker->get('field_documents_description')->value) {
      $build['#markup'] = '<div class="container">' . $policymaker->get('field_documents_description')->value . '<div>';
    }

    return $build;
  }

  /**
   * Return title as translatable string.
   */
  public static function getDocumentsTitle() {
    return t('Documents');
  }

  /**
   * Policymaker decisions route.
   */
  public function decisions($organization) {
    $build = ['#title' => t('Decisions: @title', ['@title' => $this->policymakerService->getPolicymaker()->get('title')->value])];
    return $build;
  }

  /**
   * Return title as translatable string.
   */
  public static function getDecisionsTitle() {
    return t('Decisions');
  }

  /**
   * Policymaker dicussion minutes route.
   */
  public function discussionMinutes() {
    $build = ['#title' => t('Discussion minutes: @title', ['@title' => $this->policymakerService->getPolicymaker()->get('title')->value])];

    $minutes = $this->policymakerService->getMinutesOfDiscussion(NULL, TRUE);

    if (!empty($minutes)) {
      $build['years'] = array_keys($minutes);
      $build['list'] = $minutes;
    }

    return $build;
  }

  /**
   * Return view for singular minutes.
   */
  public function minutes($organization, $id) {
    $meetingData = $this->policymakerService->getMeetingAgenda($id);

    $build = [
      '#theme' => 'policymaker_minutes',
    ];

    if ($meetingData) {
      $build['meeting'] = $meetingData['meeting'];
      $build['list'] = $meetingData['list'];
      $build['file'] = $meetingData['file'];
    }

    return $build;
  }

  /**
   * Return title as translatable string.
   */
  public static function getDiscussionMinutesTitle() {
    return t('Discussion minutes');
  }

  /**
   * Return translatable title for minutes.
   */
  public static function getMinutesTitle() {
    return t('Minutes');
  }

}
