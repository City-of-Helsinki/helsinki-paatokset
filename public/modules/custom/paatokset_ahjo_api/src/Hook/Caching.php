<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Hook;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\paatokset_ahjo_api\Entity\Decision;
use Drupal\paatokset_ahjo_api\Entity\Meeting;

/**
 * Cache invalidation hooks.
 */
readonly class Caching {

  public function __construct(
    private CacheTagsInvalidatorInterface $cacheTagsInvalidator,
  ) {
  }

  /**
   * Implements hook_ENTITY_TYPE_insert().
   */
  #[Hook('node_insert')]
  public function insert(EntityInterface $entity): void {
    if ($entity instanceof Decision) {
      $this->invalidateDecisionCacheTags($entity);
    }
    elseif ($entity instanceof Meeting) {
      $this->invalidateMeetingCacheTags($entity);
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_update().
   */
  #[Hook('node_update')]
  public function update(EntityInterface $entity): void {
    if ($entity instanceof Decision) {
      $this->invalidateDecisionCacheTags($entity);
    }
    elseif ($entity instanceof Meeting) {
      $this->invalidateMeetingCacheTags($entity);
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_delete().
   */
  #[Hook('node_delete')]
  public function delete(EntityInterface $entity): void {
    if ($entity instanceof Decision) {
      $this->invalidateDecisionCacheTags($entity);
    }
    elseif ($entity instanceof Meeting) {
      $this->invalidateMeetingCacheTags($entity);
    }
  }

  /**
   * Invalidates custom cache tags for decisions.
   *
   * @param \Drupal\paatokset_ahjo_api\Entity\Decision $entity
   *   Entity to base cache invalidations on.
   */
  public function invalidateDecisionCacheTags(Decision $entity): void {
    $tags = [];

    // Adding new decision invalidates case cache.
    // This allows updating a list of decisions on the
    // case page as new decisions are added.
    if ($diary_number = $entity->getDiaryNumber()) {
      $tags[] = 'ahjo_case:' . strtoupper($diary_number);
    }

    if (!$entity->get('field_meeting_id')->isEmpty()) {
      $tags[] = 'meeting:' . $entity->get('field_meeting_id')->value;
    }

    if (!$entity->get('field_policymaker_id')->isEmpty()) {
      $tags[] = 'decision_pm:' . $entity->get('field_policymaker_id')->value;
    }

    if (!empty($tags)) {
      $this->cacheTagsInvalidator->invalidateTags($tags);
    }
  }

  /**
   * Invalidates custom cache tags for meetings.
   *
   * @param \Drupal\paatokset_ahjo_api\Entity\Meeting $entity
   *   Entity to base cache invalidations on.
   */
  public function invalidateMeetingCacheTags(Meeting $entity): void {
    $tags = [];

    if ($entity->hasField('field_meeting_dm_id') && !$entity->get('field_meeting_dm_id')->isEmpty()) {
      $tags[] = 'meeting_pm:' . $entity->get('field_meeting_dm_id')->value;
    }

    if (!empty($tags)) {
      $this->cacheTagsInvalidator->invalidateTags($tags);
    }
  }

}
