<?php

namespace Drupal\paatokset_search\EventSubscriber;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\paatokset_policymakers\Service\PolicymakerService;
use Drupal\search_api\Event\ItemsIndexedEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Drupal\search_api\IndexInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * {@inheritdoc}
 */
class ItemsIndexed implements EventSubscriberInterface {

  /**
   * Class constructor.
   *
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cacheInvalidator
   *   Cache invalidator.
   */
  public function __construct(
    protected CacheTagsInvalidatorInterface $cacheInvalidator,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      SearchApiEvents::ITEMS_INDEXED => 'itemsIndexed',
    ];
  }

  /**
   * Method to handle ItemsIndexed Events.
   *
   * @param \Drupal\search_api\Event\ItemsIndexedEvent $event
   *   The ItemsIndexed event.
   */
  public function itemsIndexed(ItemsIndexedEvent $event): void {
    $index = $event->getIndex();

    // Cache tag handling for office holder decisions.
    if ($index->id() === 'decisions') {
      $tags = $this->getCacheTagsForOfficeHolderDecisions($event, $index);
    }
    elseif ($index->id() === 'meetings') {
      $tags = $this->getCacheTagsForMeetings($event, $index);
    }
    else {
      return;
    }

    if (!empty($tags)) {
      $this->cacheInvalidator->invalidateTags($tags);
    }
  }

  /**
   * Get cache tags to invalidate for office holder decisions.
   *
   * @param \Drupal\search_api\Event\ItemsIndexedEvent $event
   *   The ItemsIndexed event.
   * @param \Drupal\search_api\IndexInterface $index
   *   The index to act on.
   *
   * @return array
   *   Array of cache tags to invalidate.
   */
  private function getCacheTagsForOfficeHolderDecisions(ItemsIndexedEvent $event, IndexInterface $index): array {
    $tags = [];

    $ids = $event->getProcessedIds();
    $items = $index->loadItemsMultiple($ids);
    foreach ($items as $item) {
      $properties = $item->getProperties();
      if (empty($properties['field_organization_type']) || empty($properties['field_policymaker_id'])) {
        continue;
      }

      // Only invalidate cache tags for office holders.
      // They are the only ones currently that are fetched from ElasticSearch.
      /** @var \Drupal\Core\Field\FieldItemList $org_type_field */
      $org_type_field = $item->get('field_organization_type');
      if ($org_type_field->isEmpty() || !in_array($org_type_field->value, PolicymakerService::TRUSTEE_TYPES)) {
        continue;
      }

      /** @var \Drupal\Core\Field\FieldItemList $dm_id_field */
      $dm_id_field = $item->get('field_policymaker_id');
      if ($dm_id_field->isEmpty()) {
        continue;
      }

      $tag = 'decision_pm:' . $dm_id_field->value;
      if (!in_array($tag, $tags)) {
        $tags[] = $tag;
      }

    }
    return $tags;
  }

  // phpcs:disable
  /**
   * Get cache tags to invalidate for meetings.
   *
   * @param \Drupal\search_api\Event\ItemsIndexedEvent $event
   *   The ItemsIndexed event.
   * @param \Drupal\search_api\IndexInterface $index
   *   The index to act on.
   *
   * @return array
   *   Array of cache tags to invalidate.
   */
  private function getCacheTagsForMeetings(ItemsIndexedEvent $event, IndexInterface $index): array {
    $tags = [];
    return $tags;
    $ids = $event->getProcessedIds();
    $items = $index->loadItemsMultiple($ids);
    foreach ($items as $item) {
      $properties = $item->getProperties();
      if (empty($properties['field_meeting_dm_id'])) {
        continue;
      }
      /** @var \Drupal\Core\Field\FieldItemList $dm_id_field */
      $dm_id_field = $item->get('field_meeting_dm_id');
      if ($dm_id_field->isEmpty()) {
        continue;
      }

      $tag = 'meeting_pm:' . $dm_id_field->value;
      if (!in_array($tag, $tags)) {
        $tags[] = $tag;
      }

    }
    return $tags;
  }
  // phpcs:enable

}
