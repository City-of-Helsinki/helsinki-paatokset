<?php

declare(strict_types=1);

namespace Drupal\paatokset\Plugin\views\filter;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
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

    $entityType = $this->view->getBaseEntityType()->id();
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
  public function query(): void {
    [$year] = $this->value;

    if (empty($year)) {
      return;
    }

    [$start, $end] = match ($this->getTimeFieldType()) {
      'timestamp' => [
        mktime(0, 0, 0, 1, 1, (int) $year),
        mktime(23, 59, 59, 12, 31, (int) $year),
      ],
      'datetime' => [
        sprintf('%d-01-01T00:00:00', (int) $year),
        sprintf('%d-12-31T23:59:59', (int) $year),
      ],
    };

    $this->ensureMyTable();
    assert($this->query instanceof Sql);
    $this->query->addWhere(
      $this->options['group'],
      "$this->tableAlias.$this->realField",
      [$start, $end],
      'BETWEEN'
    );
  }

  /**
   * Adds filter condition to the query.
   */
  private function processFilterDefinition(FilterPluginBase $filter, SelectInterface $query): bool {
    return (bool) match ($filter->getPluginId()) {
      'bundle' => $query->condition("$filter->table.$filter->realField", array_keys($filter->value), 'IN'),
      // Boolean is most likely the status filter.
      'boolean' => $query->condition("$filter->table.$filter->realField", $filter->value, $filter->operator),
      // A specific filter in policymaker view that we must handle.
      'datetime' => $query->condition("$filter->table.$filter->realField", $filter->value['value'], $filter->operator),
      default => FALSE,
    };
  }

  /**
   * Get options for year select.
   */
  private function getOptions(): array {
    $baseTable = $this->view->storage->get('base_table');
    $baseField = $this->view->storage->get('base_field');
    $query = $this->connection
      ->select($baseTable, $baseTable)
      ->distinct();

    $joins = [];

    // To get accurate results, the query should somewhat match the final
    // view query. Note: this implementation is very simplified and made
    // only for our needs. This will break on more complicated views.
    foreach ($this->view->filter as $filter) {
      if ($this->processFilterDefinition($filter, $query)) {
        if ($join = $filter->getJoin()) {
          $joins[$join->table]["$baseTable.$baseField"][] = "$join->table.$join->field";
        }
      }

    }

    foreach ($this->view->argument as $argument) {
      if ($join = $argument->getJoin()) {
        $joins[$argument->table]["$baseTable.$baseField"][] = "$argument->table.$join->field";
      }

      // Filter with contextual filters.
      $query->condition("$argument->table.$argument->realField", $argument->getValue());
    }

    foreach ($this->view->relationship as $relationship) {
      if ($join = $relationship->getJoin()) {
        $joins[$join->table]["$baseTable.$baseField"] = "$join->table.$join->field";
        // Simplification: assume that the relationship links to realField.
        $joins[$this->table]["$join->table.$relationship->realField"][] = "$this->table.entity_id";
      }
    }

    if ($join = $this->getJoin()) {
      $joins[$join->table]["$baseTable.$baseField"][] = "$join->table.$join->field";
    }

    foreach ($joins as $table => $arguments) {
      foreach ($arguments as $left => $conditions) {
        foreach (array_unique($conditions) as $condition) {
          $query->innerJoin($table, $table, "$left = $condition");
        }
      }
    }

    match ($this->getTimeFieldType()) {
      'timestamp' => $query->addExpression("FROM_UNIXTIME($this->table.$this->realField, '%Y')", 'year'),
      'datetime' => $query->addExpression("YEAR($this->table.$this->realField)", 'year'),
    };

    $query->isNotNull("$this->table.$this->realField");
    $query->orderBy('year', 'DESC');

    return $query
      ->execute()
      ->fetchAllKeyed(0, 0);
  }

  /**
   * Get the configured time type.
   */
  private function getTimeFieldType(): string {
    return $this->definition['time_type'];
  }

}
