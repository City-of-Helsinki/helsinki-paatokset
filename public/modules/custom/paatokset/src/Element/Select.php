<?php

declare(strict_types=1);

namespace Drupal\paatokset\Element;

use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\Element\Hidden;

/**
 * Helfi select element.
 */
#[FormElement('helfi_select')]
class Select extends Hidden {

  /**
   * {@inheritdoc}
   */
  public function getInfo(): array {
    $info = parent::getInfo();
    $info['#process'][] = [static::class, 'processHelfiSelect'];
    return $info;
  }

  /**
   * Preprocess callback.
   */
  public static function processHelfiSelect(array $element): array {
    $element['#attached']['library'][] = 'paatokset/helfi_select';
    $element['#attached']['drupalSettings']['helfi_select']['value'] = $element['#value'];
    $element['#attached']['drupalSettings']['helfi_select']['empty_option'] = $element['#empty_option'];
    $element['#attached']['drupalSettings']['helfi_select']['options'] = $element['#options'];
    if (isset($element['#empty_option'])) {
      $key = $element['#empty_value'] ?? '';
      $element['#attached']['drupalSettings']['helfi_select']['options'][$key] = $element['#empty_option'];
    }

    // Options cause validation errors for the hidden element.
    unset($element['#options']);

    $element['#prefix'] = '<div id="helfi-select">';
		$element['#suffix'] = '</div>';
    return $element;
  }

}
