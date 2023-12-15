<?php

declare(strict_types=1);

namespace Drupal\paatokset_search\Plugin\search_api\processor;

use Drupal\node\NodeInterface;
use Drupal\paatokset_policymakers\Service\OrganizationPathBuilder;
use Drupal\search_api\Processor\ProcessorPluginBase;

/**
 * This processor forms the organization hierarchy for the index.
 *
 * @SearchApiProcessor(
 *    id = "organization_hierarchy",
 *    label = @Translation("Show organizations as hierarchy"),
 *    description = @Translation("Full organization path in hierarchical order."),
 *    stages = {
 *      "alter_items" = -50
 *    }
 * )
 */
final class OrganizationHierarchy extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function alterIndexedItems(array &$items): void {
    /** @var \Drupal\search_api\Item\ItemInterface $item */
    foreach ($items as $item_id => $item) {
      $object = $item->getOriginalObject()->getValue();

      // Skip trustees as they don't have organization field.
      if (!$object instanceof NodeInterface || $object->getType() === 'trustee') {
        continue;
      }

      $organizations = \Drupal::service(OrganizationPathBuilder::class)->build($object);
      $organization_hierarchy = [];

      if ($organizations) {
        foreach ($organizations['#organizations'] as $organization) {
          $organization_hierarchy[] = $organization['title'];
        }
      }

      $field = $item->getField('organization_hierarchy');
      $field->setValues($organization_hierarchy);
    }

  }

}
