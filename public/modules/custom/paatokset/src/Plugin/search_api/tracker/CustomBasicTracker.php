<?php

namespace Drupal\paatokset\Plugin\search_api\tracker;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Attribute\SearchApiTracker;
use Drupal\search_api\Tracker\TrackerPluginBase;

/**
 * The default tracked with improved indexing order.
 */
#[SearchApiTracker(
  id: 'custom_basic_tracker',
  label: new TranslatableMarkup('Customized basic tracker'),
  description: new TranslatableMarkup('Based on the default tracker. Improved indexing order.')
)]
class CustomBasicTracker extends TrackerPluginBase implements PluginFormInterface {

  /**
   * {@inheritDoc}
   *
   * The item_id field values are strings (f.ex. entity:node/123:fi) and
   * that's why "order by" does not work properly. Value must be cast to int.
   */
  protected function createRemainingItemsStatement($datasource_id = NULL): SelectInterface {
    $select = $this->createSelectStatement();
    $select->fields('sai', ['item_id']);
    if ($datasource_id) {
      $select->condition('datasource', $datasource_id);
    }

    $select->addExpression("CAST(REGEXP_REPLACE(item_id, :pattern, :replacement) as unsigned)", 'numeric_item_id', [
      'pattern' => '[^0-9]',
      'replacement' => '',
    ]);

    $select->condition('sai.status', $this::STATUS_NOT_INDEXED, '=');
    $select->orderBy('numeric_item_id', 'DESC');

    return $select;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [];
  }

}
