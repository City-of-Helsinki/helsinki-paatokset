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
class Policymaker extends Node implements AhjoEntityInterface {

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

    // Use org type to determine color coding.
    return match ($this->getOrganizationType()) {
      OrganizationType::COUNCIL => 'color-kupari',
      OrganizationType::CABINET => 'color-hopea',
      OrganizationType::OFFICE_HOLDER => 'color-suomenlinna',
      OrganizationType::TRUSTEE => 'color-engel',
      OrganizationType::BOARD => 'color-sumu',
      default => 'color-none',
    };
  }

  /**
   * Get organization name.
   *
   * @param bool $get_ahjo_title
   *   Get Ahjo title instead of node title.
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
   * Get organization type.
   */
  public function getOrganizationType(): ?OrganizationType {
    if (!$this->get('field_organization_type')->isEmpty()) {
      return OrganizationType::tryFromOrganizationType($this->get('field_organization_type')->value);
    }

    return NULL;
  }

  /**
   * Returns true if policymaker is trustee.
   *
   * @return bool
   *   True if policymaker is trustee. If false, policymaker is an organization.
   */
  public function isTrustee(): bool {
    return $this->getOrganizationType()?->isTrustee() ?: FALSE;
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
    if (!$this->isTrustee()) {
      return NULL;
    }

    ['decision' => $route] = PolicymakerRoutes::getRoutes($langcode, $this->getOrganizationType());

    if (PolicymakerService::routeExists($route)) {
      return Url::fromRoute($route, [
        'organization' => strtolower($this->getPolicymakerOrganizationFromUrl($langcode)),
      ]);
    }

    return NULL;
  }

  /**
   * Get policymaker organization from URL.
   *
   * Policymakers are available from two different URL structures:
   *  - /fi/paattajat/kaupunginvaltuusto/*
   *    Handles by "policymaker.page.$langcode" route.
   *  - /fi/paattajat/02900/*
   *    URL alias handles by Drupal.
   *
   * This is used when generating policymaker related URLs in
   * attempt to preserve the currently used URL format.
   *
   * @todo could this just slugify Ahjo title? UHF-11726
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
    if ($policymaker->language()->getId() !== $langcode && !$policymaker->get('field_policymaker_id')->isEmpty()) {
      return strtolower($policymaker->get('field_policymaker_id')->value);
    }

    $policymaker_url = $policymaker->toUrl()->toString();
    $policymaker_url_bits = explode('/', $policymaker_url);
    $policymaker_org = array_pop($policymaker_url_bits);
    return strtolower($policymaker_org);
  }

  /**
   * Get policymaker organization.
   *
   * @returns ?Organization
   *    Organizations of the given policymaker. Null if the policymaker does not
   *    belong to any organization (we get invalid data from API).
   */
  public function getOrganization(): ?Organization {
    $organization = \Drupal::entityTypeManager()
      ->getStorage('ahjo_organization')
      ->load($this->getPolicymakerId());
    assert($organization === NULL || $organization instanceof Organization);

    if ($organization?->hasTranslation($this->language()->getId())) {
      return $organization->getTranslation($this->language()->getId());
    }

    return $organization;
  }

  /**
   * {@inheritDoc}
   */
  public function getProxyUrl(): Url {
    return Url::fromRoute('paatokset_ahjo_proxy.decisionmaker_single', [
      'id' => $this->getAhjoId(),
    ]);
  }

  /**
   * {@inheritDoc}
   */
  public function getAhjoId(): string {
    return $this->get('field_policymaker_id')->getString();
  }

}
