<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\EventSubscriber;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\paatokset_ahjo_api\Entity\OrganizationType;
use Drupal\search_api\Event\ItemsIndexedEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Drupal\search_api\IndexInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * What does this do?
 *
 * @todo is this still required?
 * @todo figure out (and document) where these cache tags are used.
 */
class ItemsIndexed implements EventSubscriberInterface {

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

    $tags = match ($index->id()) {
      'decisions' => $this->getCacheTagsForOfficeHolderDecisions($event, $index),
      'meetings' => $this->getCacheTagsForMeetings($event, $index),
      default => [],
    };

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

      /** @var \Drupal\Core\Field\FieldItemList $org_type_field */
      $org_type_field = $item->get('field_organization_type');
      if ($org_type_field->isEmpty() || !in_array($org_type_field->value, OrganizationType::TRUSTEE_TYPES)) {
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

}
