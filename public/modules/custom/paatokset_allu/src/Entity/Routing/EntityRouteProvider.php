<?php

declare(strict_types=1);

namespace Drupal\paatokset_allu\Entity\Routing;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Drupal\paatokset_allu\Controller\Controller;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides routes for content entities.
 */
class EntityRouteProvider extends AdminHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  public function getRoutes(EntityTypeInterface $entity_type): RouteCollection|array {
    $collection = parent::getRoutes($entity_type);

    $route = (new Route('/allu/document/{paatokset_allu_document}/download'))
      ->addDefaults([
        '_controller' => Controller::class . '::decision',
      ])
      ->setRequirement('paatokset_allu_document', '\d+')
      ->setRequirement('_entity_access', 'paatokset_allu_document.view');
    $collection->add('entity.paatokset_allu_document.canonical', $route);

    $route = (new Route('/allu/document/{paatokset_allu_document}/approval/{type}/download'))
      ->addDefaults([
        '_controller' => Controller::class . '::approval',
      ])
      ->setRequirement('paatokset_allu_document', '\d+')
      ->setRequirement('_entity_access', 'paatokset_allu_document.view');
    $collection->add('entity.paatokset_allu_document.approval', $route);

    return $collection;
  }

}
