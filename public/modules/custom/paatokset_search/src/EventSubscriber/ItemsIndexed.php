<?php

namespace Drupal\paatokset_search\EventSubscriber;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\search_api\Event\ItemsIndexedEvent;
use Drupal\search_api\Event\SearchApiEvents;
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

    // Only act on decision nodes.
    if ($index->id() !== 'decisions') {
      return;
    }

    $ids = $event->getProcessedIds();
    $items = $index->loadItemsMultiple($ids);
    $tags = [];
    foreach ($items as $item) {
      $properties = $item->getProperties();
      if (empty($properties['field_organization_type']) || empty($properties['field_policymaker_id'])) {
        continue;
      }

      // Only invalidate cache tags for office holders.
      // They are the only ones currently that are fetched from ElasticSearch.
      /** @var \Drupal\Core\Field\FieldItemList $org_type_field */
      $org_type_field = $item->get('field_organization_type');
      if ($org_type_field->isEmpty() || $org_type_field->value !== 'Viranhaltija') {
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

    if (!empty($tags)) {
      $this->cacheInvalidator->invalidateTags($tags);
    }
  }

}
