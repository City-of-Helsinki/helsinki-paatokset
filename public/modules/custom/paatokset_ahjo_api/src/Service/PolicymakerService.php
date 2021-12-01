<?php

namespace Drupal\paatokset_ahjo_api\Service;

use Drupal\node\Entity\Node;

/**
 * Service class for retrieving policymaker-related data.
 *
 * @package Drupal\paatokset_ahjo_api\Serivces
 */
class PolicymakerService {
  /**
   * Machine name for meeting node type.
   */
  const NODE_TYPE = 'policymaker';

  /**
   * Query policymakers from database.
   *
   * @param array $params
   *   Containing query parameters
   *   $params = [
   *     sort  => (string) ASC or DESC.
   *     limit => (int) Limit results.
   *     policymaker => (string) policymaker ID.
   *   ].
   *
   * @return array
   *   of policymakers.
   */
  public function query(array $params = []) : array {
    if (isset($params['sort'])) {
      $sort = $params['sort'];
    }
    else {
      $sort = 'ASC';
    }

    $query = \Drupal::entityQuery('node')
      ->condition('status', 1)
      ->condition('type', self::NODE_TYPE)
      ->sort('created', $sort);

    if (isset($params['limit'])) {
      $query->range('0', $params['limit']);
    }

    if (isset($params['policymaker'])) {
      $query->condition('field_policymaker_id', $params['policymaker']);
    }

    $ids = $query->execute();

    if (empty($ids)) {
      return [];
    }

    return Node::loadMultiple($ids);
  }

  /**
   * Get policymaker node by ID.
   *
   * @param string $id
   *   Policymaker id.
   *
   * @return Drupal\node\NodeInterface|null
   *   Policymaker node or NULL.
   */
  public function getPolicyMaker(string $id): ?Node {
    $queryResult = $this->query([
      'limit' => 1,
      'policymaker' => $id,
    ]);

    return reset($queryResult);
  }

}
