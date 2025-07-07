<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Service;

/**
 * Service for converting default texts to render_array.
 */
class DefaultTextProcessor {

  /**
   * Process default text array to render_array.
   *
   * @param array $configuration_array
   *   The default text as an array.
   *
   * @return array
   *   Returns render_array.
   */
  public function process(array $configuration_array): array {
    return [
      '#type' => 'processed_text',
      '#text' => $configuration_array['value'] ?? '',
      '#format' => $configuration_array['format'] ?? 'full_html',
    ];
  }

}
