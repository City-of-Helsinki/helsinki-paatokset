<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\search_api\processor;

use Drupal\paatokset_ahjo_api\Entity\Policymaker;
use Drupal\paatokset_ahjo_api\Service\OrganizationPathBuilder;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * This processor forms the organization hierarchy for the index.
 *
 * @SearchApiProcessor(
 *    id = "organization_hierarchy",
 *    label = @Translation("Show organizations as hierarchy"),
 *    description = @Translation("Full organization path in hierarchical order."),
 *    stages = {
 *      "add_properties" = 0
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
  public function getPropertyDefinitions(?DataSourceInterface $datasource = NULL): array {
    $properties = [];

    if ($datasource) {
      $definition = [
        'label' => $this->t('Show organizations as hierarchy'),
        'description' => $this->t('Full organization path in hierarchical order.'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
        'hidden' => TRUE,
        'is_list' => TRUE,
      ];
      $properties['organization_hierarchy'] = new ProcessorProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item): void {
    $object = $item->getOriginalObject()->getValue();

    // Only policymakers have the organization field.
    if (!$object instanceof Policymaker) {
      return;
    }

    $organizations = $this->organizationPathBuilder->build($object);
    $organization_hierarchy = array_map(static fn (array $org) => $org['title'], $organizations['#organizations'] ?? []);

    $fields = $this
      ->getFieldsHelper()
      ->filterForPropertyPath($item->getFields(), $item->getDatasourceId(), 'organization_hierarchy');

    foreach ($fields as $field) {
      foreach ($organization_hierarchy as $organization) {
        $field->addValue($organization);
      }
    }
  }

}
