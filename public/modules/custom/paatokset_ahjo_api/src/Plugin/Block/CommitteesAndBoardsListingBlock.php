<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a 'CommitteesAndBoardsBlock' block.
 */
#[Block(
  id: "committees_and_boards_listing",
  admin_label: new TranslatableMarkup("Committees and Boards Listing Block"),
)]
final class CommitteesAndBoardsListingBlock extends BlockBase implements ContainerFactoryPluginInterface {


  /**
   * {@inheritDoc}
   */
  public function build(): array {
    return [
      '#attributes' => [
        'class' => ['committees-and-boards-listing'],
      ],
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function getCacheTags(): array {
    return [
      'node_list:policymaker',
      'node_list:trustee',
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function getCacheContexts(): array {
    return ['url.path', 'url.query_args'];
  }

}
