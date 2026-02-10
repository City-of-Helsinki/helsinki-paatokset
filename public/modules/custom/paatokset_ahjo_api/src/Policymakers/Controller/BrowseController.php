<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Policymakers\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\paatokset_ahjo_api\Entity\Organization;
use Drupal\paatokset_ahjo_api\Entity\Policymaker;
use Drupal\paatokset_ahjo_api\Service\PolicymakerService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for browsing policymakers.
 *
 * This controller uses PathProcessor for translating the URL.
 *
 * @see \Drupal\paatokset_ahjo_api\Policymakers\PathProcessor
 */
class BrowseController extends ControllerBase {

  use AutowireTrait;

  public function __construct(
    private readonly PolicymakerService $policymakerService,
    private readonly EntityRepositoryInterface $entityRepository,
  ) {
  }

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
    $children = array_map(fn ($org) => $this->entityRepository->getTranslationFromContext($org), $children);

    $policymakers = $this->loadPolicymakers([$org->id(), ...array_keys($children)]);

    $tags = [];
    foreach ($policymakers as $id => $policymaker) {
      $tags[$id] = $this->policymakerService->getPolicymakerTag($policymaker);
    }

    $breadcrumb = '';

    if ($parent = $org->getParentOrganization()) {
      $parent = $this->entityRepository->getTranslationFromContext($parent) ?: $parent;

      // Special case for the first organizations:
      // link directly to the root level.
      if ($parent->id() == '00001') {
        $breadcrumb = Link::createFromRoute(new TranslatableMarkup('City of Helsinki'), 'paatokset_ahjo_api.browse_policymakers', [], [
          'attributes' => ['class' => ['policymaker-browser__breadcrumb__link']],
        ]);
      }
      else {
        $breadcrumb = Link::createFromRoute($parent->label(), 'paatokset_ahjo_api.browse_policymakers', [
          'org' => $parent->id(),
        ], [
          'attributes' => ['class' => ['policymaker-browser__breadcrumb__link']],
        ]);
      }
    }

    $build = [
      '#theme' => 'policymaker_browser',
      '#organization' => $org,
      '#children' => $children,
      '#policymakers' => $policymakers,
      '#breadcrumb' => $breadcrumb,
      '#tags' => $tags,
    ];

    $cache = new CacheableMetadata();
    foreach ([$org, ...$children, ...$policymakers] as $entity) {
      $cache->addCacheableDependency($entity);
    }
    $cache->applyTo($build);

    return $build;
  }

  /**
   * Title callback for policymaker browse page.
   */
  public function title(Organization|null $org): string|TranslatableMarkup {
    if ($label = $org?->label()) {
      return $label;
    }

    return new TranslatableMarkup('Browse decisionmakers');
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
    $children = array_map(fn ($org) => $this->entityRepository->getTranslationFromContext($org), $children);

    $policymakers = $this->loadPolicymakers($rootIds);

    $tags = [];
    foreach ($policymakers as $id => $policymaker) {
      $tags[$id] = $this->policymakerService->getPolicymakerTag($policymaker);
    }

    $build = [
      '#theme' => 'policymaker_browser',
      '#children' => $children,
      '#policymakers' => $policymakers,
      '#tags' => $tags,
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

    $entityRepository = $this->entityRepository;

    // Load all policymakers that are present on this page.
    // Arrange the policymakers by their ahjo id for easy
    // access on the template.
    return array_reduce(
      $this
        ->entityTypeManager()
        ->getStorage('node')
        ->loadMultiple($policymakerIds),
      function (array $array, Policymaker $policymaker) use ($entityRepository) {
        $array[$policymaker->getPolicymakerId()] = $entityRepository->getTranslationFromContext($policymaker);
        return $array;
      },
      []
    );
  }

}
