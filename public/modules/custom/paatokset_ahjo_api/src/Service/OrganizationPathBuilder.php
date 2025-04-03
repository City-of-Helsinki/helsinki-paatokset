<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Service;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\paatokset_ahjo_api\Entity\Organization;
use Drupal\paatokset_ahjo_api\Entity\Policymaker;

/**
 * Service for displaying the organization hierarchy.
 */
class OrganizationPathBuilder {

  /**
   * Build organization hierarchy for policymaker node.
   *
   * @param \Drupal\paatokset_ahjo_api\Entity\Policymaker $policymaker
   *   Policymaker entity.
   *
   * @return array
   *   Organization hierarchy build array.
   */
  public function build(Policymaker $policymaker): array {
    if (!$organization = $policymaker->getOrganization()) {
      return [];
    }

    // Do not display the first organization. For each organization, this is
    // the city itself.
    $hierarchy = $organization->getOrganizationHierarchy();
    $hierarchy = array_slice($hierarchy, 1);

    // Don't bother printing org path if there's only one item.
    // It would only duplicate the current org's title.
    if (count($hierarchy) <= 1) {
      return [];
    }

    $build = [];

    $cache = new CacheableMetadata();
    $cache->addCacheableDependency($policymaker);

    foreach ($hierarchy as $org) {
      /** @var \Drupal\paatokset_ahjo_api\Entity\Organization $org */
      $build['#organizations'][] = $this->buildOrganizationHierarchyItem($org);
      $cache->addCacheableDependency($org);
    }

    if (!empty($build['#organizations'])) {
      $build['#theme'] = 'organization_path';
    }

    $cache->applyTo($build);

    return $build;
  }

  /**
   * Build organization hierarchy.
   *
   * @param \Drupal\paatokset_ahjo_api\Entity\Organization $organization
   *   Organization node.
   *
   * @return array
   *   Render array
   */
  private function buildOrganizationHierarchyItem(Organization $organization): array {
    return [
      'title' => $organization->label(),
      'langcode' => $organization->get('langcode')->value,
    ];
  }

}
