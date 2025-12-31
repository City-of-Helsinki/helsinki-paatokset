<?php

/**
 * @file
 * Contains installation hooks for Päätökset Search module.
 */

declare(strict_types=1);

use Drupal\node\Entity\Node;

/**
 * Set field_policymaker_existing field on all trustee nodes.
 */
function paatokset_search_deploy_0001_trustee_field(): void {
  $query = \Drupal::entityQuery('node');
  $nids = $query->accessCheck(TRUE)
    ->condition('status', 1)
    ->notExists('field_policymaker_existing')
    ->condition('type', 'trustee')
    ->execute();
  if (!$nids) {
    return;
  }

  $nodes = Node::loadMultiple($nids);

  foreach ($nodes as $node) {
    $node->set('field_policymaker_existing', 1);
    $node->save();
  }
}
