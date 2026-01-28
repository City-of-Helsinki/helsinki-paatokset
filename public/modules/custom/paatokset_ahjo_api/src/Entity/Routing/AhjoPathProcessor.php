<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Entity\Routing;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Symfony\Component\HttpFoundation\Request;

/**
 * Handles translations for Ahjo case paths.
 *
 * Drupal does not support translations for entity canonical URLs.
 */
final readonly class AhjoPathProcessor implements InboundPathProcessorInterface, OutboundPathProcessorInterface {

  public function __construct(private LanguageManagerInterface $languageManager) {
  }

  /**
   * {@inheritDoc}
   */
  public function processInbound($path, Request $request): string {
    $paths = [
      // @todo remove v2 prefix.
      '/v2/case' => [
        'fi' => '/^\/v2\/asia\/([\p{L}\p{N}\-]+)$/ui',
        'sv' => '/^\/v2\/arende\/([\p{L}\p{N}\-]+)$/ui',
        'en' => '/^\/v2\/case\/([\p{L}\p{N}\-]+)$/ui',
      ],
    ];

    $langcode = $this->languageManager
      ->getCurrentLanguage()
      ->getId();

    foreach ($paths as $prefix => $regexes) {
      // Match the exact path pattern.
      if (isset($regexes[$langcode]) && preg_match($regexes[$langcode], $path, $matches)) {
        // Return the internal path that the entity expects.
        return $prefix . '/' . strtoupper($matches[1]);
      }
    }

    // Return the original path.
    return $path;
  }

  /**
   * {@inheritDoc}
   */
  public function processOutbound($path, &$options = [], ?Request $request = NULL, ?BubbleableMetadata $bubbleable_metadata = NULL): string {
    $paths = [
      // @todo remove v2 prefix.
      '/^\/v2\/case\/([\p{L}\p{N}\-]+)$/u' => [
        'fi' => '/v2/asia',
        'sv' => '/v2/arende',
        'en' => '/v2/case',
      ],
    ];

    foreach ($paths as $pattern => $translations) {
      if (preg_match($pattern, $path, $matches)) {
        if (isset($options['language'])) {
          $langcode = $options['language'];

          if ($options['language'] instanceof LanguageInterface) {
            $langcode = $options['language']->getId();
          }
        }
        else {
          $langcode = $this->languageManager->getCurrentLanguage()->getId();
        }

        if ($translated = $translations[$langcode] ?? NULL) {
          return $translated . '/' . strtolower($matches[1]);
        }
      }
    }

    return $path;
  }

}
