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
    $info['#attached']['library'][] = 'paatokset/helfi_select';
    return $info;
  }

  /**
   * Preprocess callback.
   */
  public static function processHelfiSelect(array $element): array {
    $settings['value'] = $element['#value'];
    $settings['options'] = $element['#options'];
    if (isset($element['#empty_option'])) {
      $key = $element['#empty_value'] ?? '';
      $settings['options'][$key] = $element['#empty_option'];
      $settings['empty_option'] = $element['#empty_option'];
    }

    $element['#attributes']['data-helfi-select-settings'] = json_encode($settings);

    // Options cause validation errors for the hidden element.
    unset($element['#options']);

    $element['#prefix'] = '<div id="helfi-select" class="form-item">';
    $element['#suffix'] = '</div>';
    return $element;
  }

}
