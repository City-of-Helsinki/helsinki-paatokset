<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\node\NodeInterface;
use Drupal\paatokset_ahjo_api\Routing\LanguageAliasGenerator;

/**
 * Generates path aliases for decisions and cases in all UI languages.
 */
readonly class LanguageAliases {

  public function __construct(
    private LanguageAliasGenerator $generator,
  ) {
  }

  /**
   * Implements hook_ENTITY_TYPE_insert() and hook_ENTITY_TYPE_update().
   */
  #[Hook('node_insert')]
  #[Hook('node_update')]
  public function generateAliases(NodeInterface $node): void {
    // Pathauto creates the alias in the node's own
    // language in the path field's postSave().
    $this->generator->generate($node);
  }

}
