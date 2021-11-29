<?php

declare(strict_types = 1);

namespace Drupal\paatokset_ahjo_api\Plugin\migrate\process;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\migrate_plus\Plugin\migrate\process\EntityLookup;

/**
 * This plugin generates entities within the process plugin.
 *
 * Based on EntityGenerate. Works with multiple values array.
 * For single value use "plugin: single_value".
 *
 * Available configuration keys:
 * - source_key: (required) the source field property that math the entity
 * field in `value_key`.
 * - values: (required) entity fields mapped to the source field properties.
 *
 * @MigrateProcessPlugin(
 *   id = "ahjo_entity_generate"
 * )
 *
 * @see EntityLookup, EntityGenerate
 */
class AhjoEntityGenerate extends EntityLookup {

  /**
   * The lookup source key.
   *
   * @var string
   */
  protected string $lookupSourceKey;

  /**
   * The row from the source to process.
   *
   * @var \Drupal\migrate\Row
   */
  protected Row $row;

  /**
   * The migrate executable.
   *
   * @var \Drupal\migrate\MigrateExecutableInterface
   */
  protected MigrateExecutableInterface $migrateExecutable;

  /**
   * Performs the associated process.
   *
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function transform($value, MigrateExecutableInterface $migrateExecutable, Row $row, $destinationProperty) {
    // If the source data is an empty array, return the same.
    if (gettype($value) === 'array' && count($value) === 0) {
      return [];
    }
    $this->row = $row;
    $this->migrateExecutable = $migrateExecutable;
    $this->lookupSourceKey = $this->configuration['source_key'];

    // Creates an entity if the lookup determines it doesn't exist.
    $lookup_value = $value[$this->lookupSourceKey] ?? [];
    if (!($result = parent::transform($lookup_value, $migrateExecutable, $row, $destinationProperty))) {
      $result = $this->generateEntity($value);
    }
    /** @var \Drupal\Core\Entity\FieldableEntityInterface $existing */
    elseif ($existing = $this->entityTypeManager->getStorage($this->lookupEntityType)
      ->load($result)) {
      $this->updateEntity($existing, $value);
    }

    return $result;
  }

  /**
   * Generates an entity for a given value.
   *
   * @param mixed $value
   *   Value to use in creation of the entity.
   *
   * @return int|string|null
   *   The entity id of the generated entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function generateEntity($value) {
    if (empty($value)) {
      return;
    }
    if (!is_array($value)) {
      return;
    }

    $entity = $this->entityTypeManager
      ->getStorage($this->lookupEntityType)
      ->create($this->entity($value));
    $entity->save();

    return $entity->id();
  }

  /**
   * Fabricate an entity.
   *
   * This is intended to be extended by implementing classes to provide for more
   * dynamic default values, rather than just static ones.
   *
   * @param array $value
   *   Primary value to use in creation of the entity.
   *
   * @return array
   *   Entity value array.
   */
  protected function entity(array $value): array {
    $entity_values = [];

    if ($this->lookupBundleKey) {
      $entity_values[$this->lookupBundleKey] = $this->lookupBundle;
    }
    // Gather any static default values for properties/fields.
    if (isset($this->configuration['default_values']) && is_array($this->configuration['default_values'])) {
      foreach ($this->configuration['default_values'] as $key => $default_value) {
        NestedArray::setValue($entity_values, explode(Row::PROPERTY_SEPARATOR, $key), $default_value, TRUE);
      }
    }
    foreach ($this->configuration['values'] as $key => $property) {
      if ($property === 'root_json') {
        $source_value = $value;
      }
      else {
        $source_value = $value[$property] ?? NULL;
      }

      $this->preprocessValue($key, $source_value);
      NestedArray::setValue($entity_values, explode(Row::PROPERTY_SEPARATOR, $key), $source_value, TRUE);
    }

    return $entity_values;
  }

  /**
   * Preprocess a parameter value if needed.
   *
   * @param string $name
   *   The name of the param.
   * @param mixed $value
   *   The value of the param.
   */
  protected function preprocessValue(string $name, &$value): void {
    if ($value === NULL) {
      return;
    }
    if ($name === 'root_json') {
      $value = json_encode($value);
    }
  }

  /**
   * Update entity fields.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity to update.
   * @param array $values
   *   The new values.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  protected function updateEntity(FieldableEntityInterface $entity, array $values): void {
    $updated = FALSE;
    foreach ($this->configuration['values'] as $field_name => $property) {
      if ($field_name == $this->lookupValueKey || $field_name == $this->lookupBundleKey) {
        continue;
      }

      if ($property === 'root_json') {
        $source_value = $values;
      }
      else {
        $source_value = $values[$property] ?? NULL;
      }

      $this->preprocessValue($property, $source_value);

      $field = $entity->get($field_name);
      if ($field->isEmpty() && !isset($source_value)) {
        continue;
      }
      if ($field->value == $source_value) {
        continue;
      }
      $field->set(0, $source_value);
      $updated = TRUE;
    }

    if ($updated) {
      $entity->save();
    }
  }

}
