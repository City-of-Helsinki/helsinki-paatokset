<?php

declare(strict_types=1);

namespace Drupal\paatokset\Plugin\search_api\tracker;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Attribute\SearchApiTracker;
use Drupal\search_api\Plugin\search_api\tracker\Parallel;

/**
 * The parallel tracker with improved indexing order.
 */
#[SearchApiTracker(
  id: 'custom_parallel_tracker',
  label: new TranslatableMarkup('Customized parallel tracker'),
  description: new TranslatableMarkup('Based on the parallel tracker. Improved indexing order.')
)]
final class CustomParallelTracker extends Parallel {

  /**
   * {@inheritdoc}
   *
   * The item_id field values are strings (f.ex. entity:node/123:fi) and
   * that's why "order by" does not work properly. Value must be cast to int.
   */
  protected function createRemainingItemsStatement($datasource_id = NULL): SelectInterface {
    $select = parent::createRemainingItemsStatement($datasource_id);

    // Replace the order of the parent tracker.
    $order = &$select->getOrderBy();
    $order = [];

    $select->addExpression("CAST(REGEXP_REPLACE(item_id, :pattern, :replacement) as unsigned)", 'numeric_item_id', [
      'pattern' => '[^0-9]',
      'replacement' => '',
    ]);
    $select->orderBy('numeric_item_id', 'DESC');

    return $select;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    // Changed items are queued again even if they have not been indexed
    // yet, like with the customized basic tracker.
    return ['indexing_order' => 'lifo'] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);
    // The indexing order is always by the item ID.
    unset($form['indexing_order']);

    return $form;
  }

}
