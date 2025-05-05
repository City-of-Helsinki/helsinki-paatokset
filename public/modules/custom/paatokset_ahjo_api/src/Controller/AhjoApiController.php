<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\paatokset_ahjo_api\Entity\Organization;
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
    $language = $this->languageManager()->getCurrentLanguage()->getId();

    $response = new CacheableJsonResponse();
    $response->setData($this->buildOrgCharg($ahjo_organization, $response, $steps, $language));

    // Cache by current language.
    $cacheMetadata = new CacheableMetadata();
    $cacheMetadata->addCacheContexts(['languages:language_content']);
    $response->addCacheableDependency($cacheMetadata);

    return $response;
  }

  /**
   * Build org chart.
   */
  private function buildOrgCharg(Organization $organization, CacheableResponseInterface $response, int $steps, string $langcode): array {
    // Do not allow too expensive requests.
    if ($steps > 5) {
      throw new BadRequestHttpException();
    }

    $data = [
      'id' => $organization->id(),
      'title' => $organization->label(),
    ];

    $response->addCacheableDependency($organization);

    if ($steps > 1) {
      foreach ($organization->getChildOrganizations() as $child) {
        if ($child->hasTranslation($langcode)) {
          $child = $child->getTranslation($langcode);
        }

        $data['children'][] = $this->buildOrgCharg($child, $response, $steps - 1, $langcode);
      };
    }

    return $data;
  }

}
