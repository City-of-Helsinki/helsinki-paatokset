<?php

declare(strict_types=1);

namespace Drupal\paatokset_policymakers\Service;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\node\NodeInterface;
use Webmozart\Assert\Assert;

/**
 * Service for displaying the organization hierarchy.
 */
final readonly class OrganizationPathBuilder {

  /**
   * Constructs a new OrganizationPathBuilder object.
   *
   * @param \Drupal\paatokset_policymakers\Service\OrganizationService $organizationService
   *   The organization service.
   */
  public function __construct(private OrganizationService $organizationService) {
  }

  /**
   * Build organization hierarchy for policymaker node.
   *
   * @param \Drupal\node\NodeInterface $policymaker
   *   Policymaker node.
   *
   * @return array
   *   Organization hierarchy build array.
   */
  public function build(NodeInterface $policymaker): array {
    Assert::eq($policymaker->bundle(), 'policymaker');

    if (!$organization = $this->organizationService->getPolicymakerOrganization($policymaker)) {
      return [];
    }

    $hierarchy = $this->organizationService->getOrganizationHierarchy($organization);

    // Don't bother printing org path if there's only one item.
    // It would only duplicate the current org's title.
    if (count($hierarchy) <= 1) {
      return [];
    }

    $build = [];

    $cache = new CacheableMetadata();
    $cache->addCacheableDependency($policymaker);

    foreach ($hierarchy as $node) {
      /** @var \Drupal\node\NodeInterface $node */
      $build['#organizations'][] = $this->buildOrganizationHierarchyItem($node);
      $cache->addCacheableDependency($node);
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
   * @param \Drupal\node\NodeInterface $organization
   *   Organization node.
   *
   * @return array
   *   Render array
   */
  private function buildOrganizationHierarchyItem(NodeInterface $organization): array {
    Assert::eq($organization->bundle(), 'organization');

    return [
      'title' => $organization->getTitle(),
      'langcode' => $organization->get('langcode')->value,
    ];
  }

}
