<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Calendar;

use Drupal\Core\Htmx\Htmx;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Builds placeholders that load calendar content with htmx.
 */
final class HtmxPlaceholder {

  /**
   * Builds a placeholder that is replaced with the content from given url.
   *
   * @param \Drupal\Core\Url $url
   *   Url of an htmx route.
   *
   * @return array<string, mixed>
   *   The render array.
   */
  public static function build(Url $url): array {
    $build = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      'message' => [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => new TranslatableMarkup('Loading meetings'),
        '#attributes' => [
          'class' => ['visually-hidden'],
          'role' => 'status',
        ],
      ],
    ];

    (new Htmx())
      ->get($url)
      ->trigger('load')
      ->swap('outerHTML')
      ->applyTo($build);

    return $build;
  }

}
