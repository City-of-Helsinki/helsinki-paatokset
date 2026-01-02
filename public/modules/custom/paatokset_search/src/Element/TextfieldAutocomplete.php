<?php

declare(strict_types=1);

namespace Drupal\paatokset_search\Element;

use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\Element\Textfield;

/**
 * Autocomplete element for location autocomplete.
 */
#[FormElement('paatokset_textfield_autocomplete')]
class TextfieldAutocomplete extends Textfield {

  /**
   * {@inheritdoc}
   */
  public function getInfo(): array {
    $info = parent::getInfo();
    $class = static::class;
    $info['#process'][] = [$class, 'processTextfieldAutocomplete'];
    $info['#attached']['library'][] = 'paatokset_search/textfield_autocomplete';
    return $info;
  }

  /**
   * Preprocess callback.
   */
  public static function processTextfieldAutocomplete(array $element): array {
    $element['#theme'] = 'paatokset_textfield_autocomplete';
    $element['#attributes']['data-paatokset-textfield-autocomplete'] = TRUE;

    $element['#wrapper_attributes']['class'] = array_merge([
      'hds-text-input',
      'hdbt-search__filter',
    ], $element['#wrapper_attributes']['class'] ?? []);

    // Remove "form-autocomplete" class.
    // This prevents Drupal autocomplete from hijacking the element.
    $element['#attributes']['class'] = array_filter(
      $element['#attributes']['class'] ?? [],
      static fn ($class) => $class !== 'form-autocomplete'
    );

    return $element;
  }

}
