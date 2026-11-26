<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Routing;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\ParamConverter\ParamConverterInterface;
use Drupal\paatokset_ahjo_api\Entity\Decision;
use Symfony\Component\Routing\Route;

/**
 * Converts decision native ID route parameter to decision node.
 *
 * Used by the case page ajax route.
 */
final readonly class DecisionConverter implements ParamConverterInterface {

  public function __construct(
    private EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * {@inheritdoc}
   *
   * @param mixed $value
   *   Decision native ID.
   * @param mixed $definition
   *   Parameter definition.
   * @param string $name
   *   Parameter name.
   * @param array<string, mixed> $defaults
   *   Route defaults.
   */
  public function convert($value, $definition, $name, array $defaults) {
    if (empty($value)) {
      return NULL;
    }

    $storage = $this->entityTypeManager->getStorage('node');

    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 1)
      ->condition('type', 'decision')
      ->condition('field_decision_native_id', Decision::bracketNativeId($value))
      ->sort('field_meeting_date', 'DESC')
      ->range(0, 1);

    // Stricter check if case_id is present.
    if (!empty($defaults['case_id'])) {
      $query->condition('field_diary_number', $defaults['case_id']);
    }

    $ids = $query->execute();

    // Entity queries are keyed by revision ID, values are entity IDs.
    return $ids ? $storage->load(array_first($ids)) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route): bool {
    return ($definition['type'] ?? NULL) === 'decision';
  }

}
