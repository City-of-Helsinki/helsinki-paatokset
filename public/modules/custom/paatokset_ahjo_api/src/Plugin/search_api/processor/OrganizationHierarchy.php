<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\search_api\processor;

use Drupal\node\NodeInterface;
use Drupal\paatokset_policymakers\Service\OrganizationPathBuilder;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * Organization path builder.
   */
  private OrganizationPathBuilder $organizationPathBuilder;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $processor */
    $processor = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $processor->organizationPathBuilder = $container->get(OrganizationPathBuilder::class);
    return $processor;
  }

  /**
   * {@inheritdoc}
   */
  public function alterIndexedItems(array &$items): void {
    /** @var \Drupal\search_api\Item\ItemInterface $item */
    foreach ($items as $item) {
      $object = $item->getOriginalObject()->getValue();

      // Skip trustees as they don't have organization field.
      if (!$object instanceof NodeInterface || $object->getType() === 'trustee') {
        continue;
      }

      $organizations = $this->organizationPathBuilder->build($object);
      $organization_hierarchy = [];

      if ($organizations) {
        foreach ($organizations['#organizations'] as $organization) {
          $organization_hierarchy[] = $organization['title'];
        }
      }

      $field = $item->getField('organization_hierarchy');
      if ($field instanceof FieldInterface) {
        $field->setValues($organization_hierarchy);
      }
    }

  }

}
