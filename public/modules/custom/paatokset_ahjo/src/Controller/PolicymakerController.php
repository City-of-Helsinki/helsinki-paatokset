<?php

namespace Drupal\paatokset_ahjo\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller class for policymaker custom routes.
 */
class PolicymakerController extends ControllerBase {

  /**
   * Policymaker documents route.
   */
  public function documents($organization) {
    // @todo implement documents page
    $build = [];
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
    $build = [];
    return $build;
  }

  /**
   * Return title as translatable string.
   */
  public static function getDecisionsTitle() {
    return t('Decisions');
  }

}
