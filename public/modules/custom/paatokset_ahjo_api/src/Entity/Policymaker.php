<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Entity;

use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\paatokset_policymakers\Enum\PolicymakerRoutes;
use Drupal\paatokset_policymakers\Service\PolicymakerService;

/**
 * Bundle class for policymaker.
 */
class Policymaker extends Node {

  /**
   * Check if policymaker is currently active.
   *
   * @return bool
   *   Policymaker existing status.
   */
  public function isActive(): bool {
    return !$this->get('field_policymaker_existing')->isEmpty() && $this->get('field_policymaker_existing')->value;
  }

  /**
   * Gets policymaker color coding from node.
   *
   * @return string
   *   Color code for label.
   */
  public function getPolicymakerClass(): string {
    // First check overridden color code.
    if (!$this->get('field_organization_color_code')->isEmpty()) {
      return $this->get('field_organization_color_code')->value;
    }

    if ($this->get('field_city_council_division')->value) {
      return 'color-hopea';
    }

    // If type isn't set, return with no color.
    if ($this->get('field_organization_type')->isEmpty()) {
      return 'color-none';
    }

    // Use org type to determine color coding.
    return match (strtolower($this->get('field_organization_type')->value)) {
      'valtuusto' => 'color-kupari',
      'hallitus' => 'color-hopea',
      'viranhaltija' => 'color-suomenlinna',
      'luottamushenkilö' => 'color-engel',
      'lautakunta', 'toimi-/neuvottelukunta', 'jaosto' => 'color-sumu',
      default => 'color-none',
    };
  }

  /**
   * Get organization name by ID.
   *
   * @param bool $get_ahjo_title
   *   Get Ahjo title instead of node title.
   *
   * @return string|null
   *   Organization name or NULL if policymaker can't be found.
   */
  public function getPolicymakerName(bool $get_ahjo_title = FALSE): ?string {
    if ($get_ahjo_title) {
      return $this->get('field_ahjo_title')->value;
    }
    return $this->title->value;
  }

  /**
   * Get policymaker ID.
   */
  public function getPolicymakerId(): ?string {
    return $this->get('field_policymaker_id')->value;
  }

  /**
   * Return route for policymaker decisions.
   *
   * @todo simplify decision/case routing logic.
   *
   * @param string $langcode
   *   Route langcode.
   *
   * @return \Drupal\Core\Url|null
   *   URL object, if route is valid.
   */
  public function getDecisionsRoute(string $langcode): ?Url {
    if (!in_array($this->get('field_organization_type')->value, PolicymakerService::TRUSTEE_TYPES)) {
      return NULL;
    }

    $routes = PolicymakerRoutes::getTrusteeRoutes();
    $baseRoute = $routes['decisions'];
    $localizedRoute = "$baseRoute.$langcode";
    $policymaker_org = $this->getPolicymakerOrganizationFromUrl($langcode);

    if (PolicymakerService::routeExists($localizedRoute)) {
      return Url::fromRoute($localizedRoute, ['organization' => strtolower($policymaker_org)]);
    }

    return NULL;
  }

  /**
   * Get policymaker organization from URL.
   *
   * @param string $langcode
   *   Langcode to get organization for.
   *
   * @return string|null
   *   Policymaker URL slug, if found.
   */
  public function getPolicymakerOrganizationFromUrl(string $langcode): ?string {
    // Attempt to switch translation.
    $policymaker = $this->hasTranslation($langcode) ? $this->getTranslation($langcode) : $this;

    // If we can't get the actual translation, return just the policymaker ID.
    if ($policymaker->get('langcode')->value !== $langcode && !$policymaker->get('field_policymaker_id')->isEmpty()) {
      return strtolower($policymaker->get('field_policymaker_id')->value);
    }

    $policymaker_url = $policymaker->toUrl()->toString(TRUE)->getGeneratedUrl();
    $policymaker_url_bits = explode('/', $policymaker_url);
    $policymaker_org = array_pop($policymaker_url_bits);
    return strtolower($policymaker_org);
  }

}
