<?php

namespace Drupal\paatokset_allu\Plugin\search_api\processor;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\paatokset_allu\Entity\Approval;
use Drupal\search_api\Item\Item;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Splits document items into the decision and one item per approval type.
 *
 * Two-stage approach:
 * - alter_items: Adds clone items (needs pass-by-reference). Clones share the
 *   original object so the pipeline's normal field extraction works on them.
 * - preprocess_index: After field extraction, overrides approval_type on each
 *   item to the target values stored in extra data. The original item keeps
 *   representing the decision document, so its approval_type is emptied.
 *
 * @SearchApiProcessor(
 *   id = "allu_approval_type_split",
 *   label = @Translation("Allu approval type split"),
 *   description = @Translation("Split items by approval_type values - creates one document per value in addition to the decision."),
 *   stages = {
 *     "alter_items" = 100,
 *     "preprocess_index" = 100
 *   }
 * )
 */
final class AlluApprovalTypeSplit extends ProcessorPluginBase {

  /**
   * Constructs a new AlluApprovalTypeSplit object.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   *
   * Determines which document items need splitting by querying the approval
   * entities directly. The original item represents the decision itself, and
   * one clone item is added per approval type. Clones share the original
   * object so the pipeline's normal getFields() extraction populates all
   * fields.
   */
  public function alterIndexedItems(array &$items): void {
    $original_ids = array_keys($items);
    foreach ($original_ids as $item_id) {
      $item = $items[$item_id];

      if ($item->getDatasourceId() !== 'entity:paatokset_allu_document') {
        continue;
      }

      $entity = $item->getOriginalObject()->getValue();
      $approvals = $this->entityTypeManager
        ->getStorage('paatokset_allu_approval')
        ->loadByProperties(['document' => $entity->id()]);

      $types = [];
      foreach ($approvals as $approval) {
        assert($approval instanceof Approval);

        $type = $approval->get('type')->value;
        if ($type && !in_array($type, $types)) {
          $types[] = $type;
        }
      }

      if (!$types) {
        continue;
      }

      // The original item is the decision document without approvals.
      $item->setExtraData('split_approval_type', []);

      foreach ($types as $delta => $type) {
        $new_id = $item_id . '__split_' . ($delta + 1);
        $new_item = new Item($item->getIndex(), $new_id);
        $new_item->setOriginalObject($item->getOriginalObject());
        $new_item->setLanguage($item->getLanguage());
        $new_item->setExtraData('split_approval_type', [$type]);
        $items[$new_id] = $new_item;
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * After the pipeline's field extraction has populated all fields (including
   * approval_type with ALL values), override approval_type on split items:
   * the decision item gets no approval type and each clone gets its single
   * target value.
   */
  public function preprocessIndexItems(array $items): void {
    foreach ($items as $item) {
      $target_types = $item->getExtraData('split_approval_type');
      if ($target_types === NULL) {
        continue;
      }

      $field = $item->getField('approval_type');
      if ($field) {
        $field->setValues($target_types);
      }
    }
  }

}
