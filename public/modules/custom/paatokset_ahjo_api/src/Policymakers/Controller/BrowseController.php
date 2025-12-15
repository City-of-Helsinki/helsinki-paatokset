<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Policymakers\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for browsing policymakers.
 *
 * This controller uses PathProcessor for translating the URL.
 *
 * @see \Drupal\paatokset_ahjo_api\Policymakers\PathProcessor
 */
class BrowseController extends ControllerBase {

  /**
   * Build policymaker browse page.
   *
   * @param string|null $policymaker_slug
   *   Policymaker slug.
   */
  public function build(string|null $policymaker_slug): array {
    // Load policymaker from slug.
    return [];
  }

}
