<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Entity\Routing;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Drupal\paatokset_ahjo_api\Controller\CaseController;
use Symfony\Component\Routing\Route;

/**
 * Provides routes for ahjo entities.
 */
final class AhjoRouteProvider extends AdminHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  protected function getCanonicalRoute(EntityTypeInterface $entity_type): ?Route {
    $route = parent::getCanonicalRoute($entity_type);

    // Use our custom controller instead of the default view builder.
    return $route
      ?->setDefault('_controller', CaseController::class . '::view');
  }

}
