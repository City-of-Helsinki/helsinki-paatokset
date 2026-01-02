<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Policymakers\Controller;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\paatokset_ahjo_api\Entity\Organization;
use Drupal\paatokset_ahjo_api\Entity\Policymaker;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
   * @param \Drupal\paatokset_ahjo_api\Entity\Organization|null $org
   *   Organization.
   */
  public function build(Organization|null $org): array {
    // Null is the default value for organization. If no
    // organization is specified, show special root level page.
    if (!$org) {
      return $this->buildRoot();
    }

    if (!$org->existing()) {
      throw new NotFoundHttpException();
    }

    $children = $org->getChildOrganizations();

    $policymakers = $this->loadPolicymakers([$org->id(), ...array_keys($children)]);

    $breadcrumb = new Breadcrumb();
    if ($parent = $org->getParentOrganization()) {
      $breadcrumb->addLink($parent->toLink());
      $breadcrumb->addCacheableDependency($parent);
    }

    $build = [
      '#theme' => 'policymaker_browser',
      '#organization' => $org,
      '#children' => $children,
      '#policymakers' => $policymakers,
      '#breadcrumb' => $breadcrumb,
    ];

    $cache = new CacheableMetadata();
    foreach ([$org, ...$children, ...$policymakers] as $entity) {
      $cache->addCacheableDependency($entity);
    }
    $cache->applyTo($build);

    return $build;
  }

  /**
   * Build the policymaker browse page at root level.
   *
   * Root level is a special case: We don't follow the city
   * organization tree here and simplify the top level hierarchy
   * a bit.
   */
  public function buildRoot(): array {
    $rootIds = ['02900', '00400'];

    $children = $this
      ->entityTypeManager()
      ->getStorage('ahjo_organization')
      ->loadMultiple($rootIds);

    $policymakers = $this->loadPolicymakers($rootIds);

    $build = [
      '#theme' => 'policymaker_browser',
      '#children' => $children,
      '#policymakers' => $policymakers,
    ];

    $cache = new CacheableMetadata();
    foreach ([...$children, ...$policymakers] as $entity) {
      $cache->addCacheableDependency($entity);
    }
    $cache->applyTo($build);

    return $build;
  }

  /**
   * Load policymakers from organization data.
   *
   * @param array<string> $ids
   *   Organization ids. Not all organizations correspond to policymaker.
   *
   * @return array<string, \Drupal\paatokset_ahjo_api\Entity\Policymaker>
   *   Organizations array. Keys are ahjo ids.
   */
  private function loadPolicymakers(array $ids): array {
    $policymakerIds = $this
      ->entityTypeManager()
      ->getStorage('node')
      ->getQuery()
      ->accessCheck()
      ->condition('type', 'policymaker')
      ->condition('status', 1)
      ->condition('field_policymaker_id', $ids, 'IN')
      ->execute();

    // Load all policymakers that are present on this page.
    // Arrange the policymakers by their ahjo id for easy
    // access on the template.
    return array_reduce(
      $this
        ->entityTypeManager()
        ->getStorage('node')
        ->loadMultiple($policymakerIds),
      static function (array $array, Policymaker $policymaker) {
        $array[$policymaker->getPolicymakerId()] = $policymaker;
        return $array;
      },
      []
    );
  }

}
