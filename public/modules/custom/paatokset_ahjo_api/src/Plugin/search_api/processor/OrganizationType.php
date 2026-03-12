<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\search_api\processor;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\Processor\ProcessorPluginBase;

/**
 * Alters the organization type field in indexed data.
 *
 * This processor probably targets `search_api.index.decisions`
 * index. The index has organization type field:
 *
 * ```
 * organization_type:
 *   label: 'Organization type'
 *   datasource_id: 'entity:node'
 *   property_path: field_organization_type
 *   type: string
 *   dependencies:
 *     config:
 *       - field.storage.node.field_organization_type
 * ```
 */
#[SearchApiProcessor(
  id: 'organization_type_fallback',
  label: new TranslatableMarkup('Organization type fallback'),
  description: new TranslatableMarkup('Uses policymaker org type as fallback if one is not set.'),
  stages: [
    'preprocess_index' => -10,
  ],
)]
class OrganizationType extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocessIndexItems(array $items): void {
    /** @var \Drupal\search_api\Item\ItemInterface $item */
    foreach ($items as $item) {
      $fields = $item->getFields();

      if (isset($fields['organization_type'])) {
        $values = $fields['organization_type']->getValues();

        if (
          !empty($values) && count($values) >= 1 && !empty($values[0])) {
          continue;
        }

        // @todo Use Policymaker::getOrganizationType() instead of accessing the field directly (requires reindexing).
        $node = $item->getOriginalObject()->getValue();
        $org_type = $node->getPolicymaker($node->language()->getId())?->get('field_organization_type')->value;

        if (!$org_type) {
          continue;
        }

        $fields['organization_type']->setValues([$org_type]);
      }
    }
  }

}
