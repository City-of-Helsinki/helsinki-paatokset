<?php

declare(strict_types=1);

namespace Drupal\paatokset\Plugin\views\filter;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\views\Plugin\views\query\Sql;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filters results by year.
 */
#[ViewsFilter("paatokset_year")]
final class YearFilter extends FilterPluginBase {

  /**
   * Database connection.
   */
  private Connection $connection;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = new self($configuration, $plugin_id, $plugin_definition);
    $instance->connection = $container->get(Connection::class);
    return $instance;
  }

  /**
   * Provide field for selecting the year.
   */
  protected function valueForm(&$form, FormStateInterface $form_state): void {
    $options = $this->getOptions();

    $form['value'] = [
      '#type' => 'helfi_select',
      '#placeholder' => $this->t('All years', [], ['context' => 'Year filter']),
      '#empty_option' => $this->t('All years', [], ['context' => 'Year filter']),
      '#options' => $options,
      '#default_value' => $this->value,
    ];

    $cache = new BubbleableMetadata();

    $entityType = $this->options['entity_type'];
    $allFilters = $this->displayHandler->getOption('filters');

    // Select element options must be invalidated when new
    // entity is inserted. Add <entity type ID>_list:<bundle>
    // cache tags (or <entity type ID>_list).
    if (isset($allFilters['type'])) {
      $cache->addCacheTags(array_map(fn ($type) => $entityType . "_list:$type", array_keys($allFilters['type']['value'])));
    }
    else {
      $cache->addCacheTags([$entityType . '_list']);
    }

    $cache->applyTo($form);
  }

  /**
   * {@inheritDoc}
   */
  public function query() {
    [$year] = $this->value;

    if (empty($year)) {
      return;
    }

    $startOfYear = mktime(0, 0, 0, 1, 1, (int) $year);
    $endOfYear = mktime(23, 59, 59, 12, 31, (int) $year);

    $this->ensureMyTable();
    assert($this->query instanceof Sql);
    $this->query->addWhere(
      $this->options['group'],
      "$this->tableAlias.$this->realField",
      [$startOfYear, $endOfYear],
      'BETWEEN'
    );
  }

  /**
   * Get options for year select.
   */
  private function getOptions(): array {
    $allFilters = $this->displayHandler->getOption('filters');

    $query = $this->connection
      ->select($this->table, 'tbl')
      ->isNotNull("tbl.$this->realField")
      ->condition("tbl.status", 1)
      ->distinct();

    // To get accurate results, this query
    // should somewhat match the final view query.
    // Query entity type if `type` filter is set.
    if (isset($allFilters['type'])) {
      [
        'field' => $field,
        'value' => $bundles,
      ] = $allFilters['type'];

      $query->condition("tbl.$field", array_keys($bundles), 'IN');
    }

    // Query with contextual filters.
    // Note: this is very simplified implementation for
    // our needs. This will break on more complicated views.
    foreach ($this->view->argument as $key => $argument) {
      $query->innerJoin($argument->table, $key, "tbl.nid = $key.entity_id");
      $query->condition("$key.$argument->field", $argument->getValue());
    }

    $query->addExpression("FROM_UNIXTIME(tbl.$this->realField, '%Y')", 'year');
    $query->orderBy('year', 'DESC');

    return $query
      ->execute()
      ->fetchAllKeyed(0, 0);
  }

}
