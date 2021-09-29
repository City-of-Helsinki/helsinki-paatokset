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
    // @todo implement documents page
    $build = ['#title' => t('Documents: @title', ['@title' => $this->policymakerService->getPolicymaker()->get('title')->value])];
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
    // @todo implement decisions page
    $build = ['#title' => t('Decisions: @title', ['@title' => $this->policymakerService->getPolicymaker()->get('title')->value])];
    return $build;
  }

  /**
   * Return title as translatable string.
   */
  public static function getDecisionsTitle() {
    return t('Decisions');
  }

}
