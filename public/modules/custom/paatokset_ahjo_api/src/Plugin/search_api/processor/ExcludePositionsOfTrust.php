<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\search_api\processor;

use Drupal\node\NodeInterface;
use Drupal\paatokset_policymakers\Service\PolicymakerService;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Webmozart\Assert\Assert;

/**
 * This processor exclude certain trustees from search index.
 *
 * Trustees are excluded if:
 *  - Trustee is not a member of city council.
 *
 * @SearchApiProcessor(
 *    id = "exclude_trustees",
 *    label = @Translation("Exclude positions of trust"),
 *    description = @Translation("Exclude non-city-council members from being indexed."),
 *    stages = {
 *      "alter_items" = -50
 *    }
 * )
 */
final class ExcludePositionsOfTrust extends ProcessorPluginBase {

  /**
   * The policymaker service.
   *
   * @var \Drupal\paatokset_policymakers\Service\PolicymakerService
   */
  private PolicymakerService $policymakerService;

  /**
   * Ids of trustee nodes that should be indexed.
   *
   * @var \Drupal\node\NodeInterface[]|null
   */
  private ?array $indexedTrusteeIds = NULL;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    /** @var static $processor */
    $processor = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $processor->policymakerService = $container->get('paatokset_policymakers');
    return $processor;
  }

  /**
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index): bool {
    foreach ($index->getDatasources() as $datasource) {
      $entity_type_id = $datasource->getEntityTypeId();
      if (!$entity_type_id) {
        continue;
      }

      // Only active on trustee nodes.
      $bundles = $datasource->getBundles();
      if ($entity_type_id === 'node' && in_array('trustee', array_keys($bundles))) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function alterIndexedItems(array &$items): void {
    /** @var \Drupal\search_api\Item\ItemInterface $item */
    foreach ($items as $item_id => $item) {
      $object = $item->getOriginalObject()->getValue();

      // The index contains policymakers as well.
      // This processor should skip all policymaker nodes.
      if (!$object instanceof NodeInterface || $object->getType() !== 'trustee') {
        continue;
      }

      // Remove from index if trustee is not part of the city council or city
      // board.
      if (!$this->shouldIndexTrustee($object)) {
        unset($items[$item_id]);
      }
    }

  }

  /**
   * Returns TRUE if given trustee node should be indexed.
   *
   * @return bool
   *   True if the node should be indexed.
   */
  private function shouldIndexTrustee(NodeInterface $trustee): bool {
    Assert::eq($trustee->getType(), 'trustee');

    return array_key_exists($trustee->id(), $this->getAllIndexedTrustees());
  }

  /**
   * Get list of all trustee nodes that should be indexed.
   *
   * If a trustee node is not in this list, it should be excluded.
   *
   * @return \Drupal\node\NodeInterface[]
   *   All trustee nodes that should be indexed.
   */
  private function getAllIndexedTrustees(): array {
    // Build indexedTrusteeIds when first called.
    if (empty($this->indexedTrusteeIds)) {
      $this->indexedTrusteeIds = [];
      $this->addPolicymakerTrustees($this->indexedTrusteeIds, PolicymakerService::CITY_COUNCIL_DM_ID);
      $this->addPolicymakerTrustees($this->indexedTrusteeIds, PolicymakerService::CITY_BOARD_DM_ID);
    }

    return $this->indexedTrusteeIds;
  }

  /**
   * Add composition of given policymaker to given &$allowedIds array.
   *
   * @param array $allowedIds
   *   Reference to allowed policymaker ids array.
   * @param string $policymakerId
   *   Policymaker id.
   */
  private function addPolicymakerTrustees(array &$allowedIds, string $policymakerId): void {
    $composition = $this->policymakerService->getTrustees($policymakerId);

    // On failure, `getTrustees` returns [].
    if (empty($composition)) {
      throw new \RuntimeException("Failed to fetch decision-maker composition");
    }

    foreach ($composition as $trustee) {
      $allowedIds[$trustee->id()] = $trustee->id();
    }
  }

}
