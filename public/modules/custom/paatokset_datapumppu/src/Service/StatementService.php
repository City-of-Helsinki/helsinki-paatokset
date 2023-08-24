<?php

declare(strict_types=1);

namespace Drupal\paatokset_datapumppu\Service;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Statement service.
 */
class StatementService {

  /**
   * Node storage service.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $statementStorage;

  /**
   * Constructs StatementService.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->statementStorage = $entity_type_manager->getStorage('paatokset_statement');
  }

  /**
   * Get statements made by trustee.
   *
   * @param \Drupal\node\NodeInterface $trustee
   *   The trustee node.
   *
   * @return \Drupal\paatokset_datapumppu\Entity\Statement[]
   *   Found statement entities.
   */
  public function getStatementsOfTrustee(NodeInterface $trustee): array {
    return $this->statementStorage->loadByProperties([
      'speaker' => $trustee->id(),
    ]);
  }

}
