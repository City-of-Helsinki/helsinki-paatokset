<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Hook;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\paatokset_ahjo_api\Entity\CaseBundle;
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
   * Implements hook_ENTITY_TYPE_insert(), _update() and _delete().
   */
  #[Hook('node_insert')]
  #[Hook('node_update')]
  #[Hook('node_delete')]
  public function invalidate(EntityInterface $entity): void {
    if ($entity instanceof Decision) {
      $this->invalidateDecisionCacheTags($entity);
    }
    elseif ($entity instanceof CaseBundle) {
      $this->invalidateCaseCacheTags($entity);
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
      $tags[] = CaseBundle::getCaseCacheTag($diary_number);
    }

    // If the decision was moved to another case, the
    // list of decisions on the old case changes too.
    $original = $entity->getOriginal();
    if ($original instanceof Decision && ($original_diary_number = $original->getDiaryNumber())) {
      $tags[] = CaseBundle::getCaseCacheTag($original_diary_number);
    }

    if (!$entity->get('field_meeting_id')->isEmpty()) {
      $tags[] = 'meeting:' . $entity->get('field_meeting_id')->value;
    }

    if (!$entity->get('field_policymaker_id')->isEmpty()) {
      $tags[] = 'decision_pm:' . $entity->get('field_policymaker_id')->value;
    }

    if (!empty($tags)) {
      $this->cacheTagsInvalidator->invalidateTags(array_values(array_unique($tags)));
    }
  }

  /**
   * Invalidates custom cache tags for cases.
   *
   * @param \Drupal\paatokset_ahjo_api\Entity\CaseBundle $entity
   *   Entity to base cache invalidations on.
   */
  public function invalidateCaseCacheTags(CaseBundle $entity): void {
    $tags = [];

    if ($diary_number = $entity->getDiaryNumber()) {
      $tags[] = CaseBundle::getCaseCacheTag($diary_number);
    }

    $original = $entity->getOriginal();
    if ($original instanceof CaseBundle && ($original_diary_number = $original->getDiaryNumber())) {
      $tags[] = CaseBundle::getCaseCacheTag($original_diary_number);
    }

    if (!empty($tags)) {
      $this->cacheTagsInvalidator->invalidateTags(array_values(array_unique($tags)));
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
