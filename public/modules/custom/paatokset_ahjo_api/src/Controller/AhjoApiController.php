<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Language\LanguageInterface;
use Drupal\paatokset_ahjo_api\Entity\Organization;
use Drupal\paatokset_ahjo_api\Entity\Policymaker;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Ahjo API controller.
 */
class AhjoApiController extends ControllerBase {

  /**
   * Get org chart.
   */
  public function getOrgChart(Organization $ahjo_organization, int $steps): Response {
    $language = $this->languageManager()->getCurrentLanguage();
    $langcode = $language->getId();

    $organisationIds = [];
    $cacheMetadata = new CacheableMetadata();
    $organisations = $this->buildOrgCharg($ahjo_organization, $organisationIds, $cacheMetadata, $steps, $langcode);

    // Recursively iterate the organization chart
    // and add policymakers node URLs to the response.
    $this->addUrlsToOrgChart($organisations, $this->loadPolicymakers($organisationIds), $language);

    $response = new CacheableJsonResponse();
    $response->setData($organisations);

    // Cache by current language.
    $cacheMetadata->addCacheContexts(['languages:language_content']);
    $response->addCacheableDependency($cacheMetadata);

    return $response;
  }

  /**
   * Build org chart.
   */
  private function buildOrgCharg(Organization $organization, array &$ids, RefinableCacheableDependencyInterface $cacheMetadata, int $steps, string $langcode): array {
    // Do not allow too expensive requests.
    if ($steps > 5) {
      throw new BadRequestHttpException();
    }

    $ids[] = $organization->id();

    $data = [
      'id' => $organization->id(),
      'title' => $organization->label(),
    ];

    $cacheMetadata->addCacheableDependency($organization);

    if ($steps > 1) {
      foreach ($organization->getChildOrganizations() as $child) {
        if ($child->hasTranslation($langcode)) {
          $child = $child->getTranslation($langcode);
        }

        $data['children'][] = $this->buildOrgCharg($child, $ids, $cacheMetadata, $steps - 1, $langcode);
      };
    }

    return $data;
  }

  /**
   * Alter org chart by adding policymaker node urls.
   *
   * @param array $orgChart
   *   Organization chart.
   * @param array<string,\Drupal\paatokset_ahjo_api\Entity\Policymaker> $policymakers
   *   Policymakers keyed by organization ids.
   * @param \Drupal\Core\Language\LanguageInterface $language
   *   The link language.
   */
  private function addUrlsToOrgChart(array &$orgChart, array $policymakers, LanguageInterface $language): void {
    $policymaker = $policymakers[$orgChart['id']] ?? NULL;

    // Not all organizations have policymaker pages.
    if ($policymaker instanceof Policymaker) {
      $orgChart['url'] = $policymaker->toUrl('canonical', ['absolute' => TRUE, 'language' => $language])->toString();
    }

    if (!empty($orgChart['children'])) {
      foreach ($orgChart['children'] as &$child) {
        $this->addUrlsToOrgChart($child, $policymakers, $language);
      }
    }
  }

  /**
   * Load policymakers.
   *
   * @param array $organisationIds
   *   List of organization ids.
   *
   * @return array<string,\Drupal\paatokset_ahjo_api\Entity\Policymaker>
   *   Policymakers keyed by organization ids.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function loadPolicymakers(array $organisationIds): array {
    $nodeStorage = $this->entityTypeManager()
      ->getStorage('node');

    // Load all policymakers from the org chart.
    $ids = $nodeStorage
      ->getQuery()
      ->accessCheck()
      ->condition('type', 'policymaker')
      ->condition('field_policymaker_id', $organisationIds, 'IN')
      ->condition('status', '1')
      ->execute();

    $policymakers = $nodeStorage->loadMultiple($ids);

    // Change array keys to organization id.
    $keys = array_map(static fn (Policymaker $policymaker) => $policymaker->getPolicymakerId(), $policymakers);

    /** @var array<string, \Drupal\paatokset_ahjo_api\Entity\Policymaker> */
    return array_combine($keys, $policymakers);
  }

}
