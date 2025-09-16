<?php

declare(strict_types=1);

namespace Drupal\paatokset\Plugin\views\filter;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityPublishedInterface;
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
   * Get options for year select.
   */
  private function getOptions(): array {
    $allFilters = $this->displayHandler->getOption('filters');

    $alias = 'tbl';
    $baseTable = $this->view->storage->get('base_table');
    $baseField = $this->view->storage->get('base_field');
    $query = $this->connection
      ->select($baseTable, 'tbl')
      ->distinct();

    // Filter published entities.
    if ($this->view->getBaseEntityType()->entityClassImplements(EntityPublishedInterface::class)) {
      $query->condition('tbl.status', 1);
    }

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
      $query->innerJoin($argument->table, $key, "tbl.$baseField = $key.entity_id");
      $query->condition("$key.$argument->field", $argument->getValue());
    }

    if ($this->table !== $baseTable) {
      $alias = $query->innerJoin($this->table, 'tbl2', "tbl.$baseField = tbl2.entity_id");
    }

    if (isset($allFilters[$this->realField])) {
      [
        'value' => $value,
        'operator' => $operator,
      ] = $allFilters[$this->realField];

      $query->condition("$alias.$this->realField", $value['value'], $operator);
    }

    match ($this->getTimeFieldType()) {
      'timestamp' => $query->addExpression("FROM_UNIXTIME($alias.$this->realField, '%Y')", 'year'),
      'datetime' => $query->addExpression("YEAR($alias.$this->realField)", 'year'),
    };

    $query->isNotNull("$alias.$this->realField");
    $query->orderBy('year', 'DESC');

    return $query
      ->execute()
      ->fetchAllKeyed(0, 0);
  }

  /**
   * Get configured time type.
   */
  private function getTimeFieldType(): string {
    return $this->definition['time_type'];
  }

}
