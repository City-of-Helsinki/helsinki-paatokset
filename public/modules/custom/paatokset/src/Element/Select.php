<?php

declare(strict_types=1);

namespace Drupal\paatokset\Element;

use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\Element\Select as CoreSelect;

/**
 * Helfi select element.
 */
#[FormElement('helfi_select')]
class Select extends CoreSelect {

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
    return $element;
  }

}
