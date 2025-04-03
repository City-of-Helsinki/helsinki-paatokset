<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
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
    $response = new CacheableJsonResponse();
    $response->setData($this->buildOrgCharg($ahjo_organization, $response, $steps));

    return $response;
  }

  /**
   * Build org chart.
   */
  private function buildOrgCharg(Organization $organization, CacheableResponseInterface $response, int $steps): array {
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
        $data['children'][] = $this->buildOrgCharg($child, $response, $steps - 1);
      };
    }

    return $data;
  }

}
