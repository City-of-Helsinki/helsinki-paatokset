<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\paatokset_ahjo_api\Entity\OrganizationType;
use Drupal\paatokset_ahjo_api\Entity\Policymaker;
use Drupal\paatokset_ahjo_api\Entity\Sector;

/**
 * Provides a 'CommitteesAndBoardsBlock' block.
 */
#[Block(
  id: "committees_and_boards_listing",
  admin_label: new TranslatableMarkup("Committees and Boards Listing Block"),
)]
final class CommitteesAndBoardsListingBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityRepositoryInterface $entityRepository,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritDoc}
   */
  public function build(): array {
    $storage = $this->entityTypeManager->getStorage('node');

    $query = $storage->getQuery()
      ->accessCheck()
      ->condition('type', 'policymaker')
      // Why do we have two status fields?
      ->condition('field_policymaker_existing', 1)
      ->condition('status', 1)
      // No idea why we need to check this. This check was
      // implemented a long time ago to PolicymakerLazyBuilder.php.
      ->exists('field_ahjo_title')
      // Don't know what this does.
      ->condition('field_city_council_division', 0)
      ->condition('field_organization_type_id', [
        // I'm terribly sorry that OrganizationType enum names don't
        // match with the real names that the city uses ¯\_(ツ)_/¯.
        // We want to query org ids 4 and 5 here.
        OrganizationType::DIVISION->value,
        OrganizationType::COMMITTEE->value,
      ], 'IN')
      ->sort('title');

    $nids = $query->execute();

    $cache = new CacheableMetadata();
    $cache->addCacheTags(['node_list:policymaker']);

    $bySectors = [];
    foreach ($storage->loadMultiple($nids) as $node) {
      assert($node instanceof Policymaker);
      if ($sector = $node->getSector()) {
        $bySectors[$sector->value][] = $this->entityRepository->getTranslationFromContext($node);
      }

      $cache->addCacheableDependency($node);
    }

    $output = [];
    foreach ($bySectors as $sector => $nodes) {
      $output[] = [
        // PHP does not support Enums as array keys.
        'sector' => Sector::from($sector),
        'links' => array_map(static fn (Policymaker $node) => $node->toLink($node->label()), $nodes),
      ];
    }

    // Sort alphabetically by sector label.
    usort($output, static fn($a, $b) => strcmp((string) $a['sector']->getLabel(), (string) $b['sector']->getLabel()));

    $build = [
      '#theme' => 'committees_and_boards_listing',
      '#committees_and_boards' => $output,
      '#attributes' => [
        'class' => ['committees-and-boards-listing'],
      ],
    ];

    $cache->applyTo($build);

    return $build;
  }

}
