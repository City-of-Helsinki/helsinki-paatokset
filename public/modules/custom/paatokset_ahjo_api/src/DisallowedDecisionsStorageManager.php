<?php

declare(strict_types = 1);

namespace Drupal\paatokset_ahjo_api;

use Drupal\Core\Config\Entity\ConfigEntityStorage;
use Drupal\Core\Entity\EntityInterface;

/**
 * The disallowed decisions storage manager class.
 */
class DisallowedDecisionsStorageManager extends ConfigEntityStorage {

  /**
   * Load and return all active disallowed decision entities.
   *
   * @return array
   *   Disallowed decisions grouped by entity.
   */
  public function getDisallowedDecisions(): array {
    $disallowed_decisions = [];
    $dd_entities = $this->loadMultiple();
    foreach ($dd_entities as $entity) {
      $values = $this->getDisallowedSectionsByYearFromEntity($entity);
      if (!empty($values)) {
        $disallowed_decisions[$entity->id()] = $values;
      }
    }

    return $disallowed_decisions;
  }

  /**
   * Load disallowed decisions config by organization ID.
   *
   * @param string $id
   *   Organization ID.
   *
   * @return array|null
   *   Disallowed sections groped by year, or NULL if entity does not exist.
   */
  public function getDisallowedDecisionsById(string $id): ?array {
    $entity = $this->load(strtolower($id));
    if (!$entity instanceof EntityInterface) {
      return NULL;
    }
    return $this->getDisallowedSectionsByYearFromEntity($entity);
  }

  /**
   * Get disallowed sections grouped by year from config entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity to get configuration data from.
   *
   * @return array|null
   *   Sections grouped by year. NULL if data is invalid or entity is hidden.
   */
  protected function getDisallowedSectionsByYearFromEntity(EntityInterface $entity): ?array {
    if (!$entity->get('status')) {
      return NULL;
    }

    $configuration = $entity->get('configuration');
    $configuration = explode('---', $configuration);
    $values = [];
    foreach ($configuration as $year) {
      $sections = explode(PHP_EOL, $year);
      $year = FALSE;
      foreach ($sections as $section) {
        $section = trim($section);
        if (empty($section)) {
          continue;
        }
        if (!$year) {
          $year = $section;
          $values[$year] = [];
          continue;
        }
        $values[$year][] = $section;
      }
    }

    if (!empty($values)) {
      return $values;
    }
    return NULL;
  }
}
