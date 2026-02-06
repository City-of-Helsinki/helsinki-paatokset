<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Policymakers;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Symfony\Component\HttpFoundation\Request;

/**
 * Handles translations for policymaker paths.
 *
 * Drupal does not support translations for controller URLs. In etusivu,
 * we have used path aliases to solve this issue. However, here we want
 * to use URL paramters for the controller, so path aliases are not an
 * option.
 */
class PathProcessor implements InboundPathProcessorInterface, OutboundPathProcessorInterface {

  public function __construct(private readonly LanguageManagerInterface $languageManager) {
  }

  /**
   * {@inheritDoc}
   */
  public function processInbound($path, Request $request): string {
    $langcode = $this->languageManager
      ->getCurrentLanguage()
      ->getId();

    try {
      $pattern = match($langcode) {
        'fi' => '/paattajat/selaa-paattajia',
        'sv' => '/beslutsfattare/bladra-bland-beslutsfattare',
        // No need to alter the English path here.
      };

      // Skip if the path does not match.
      if (str_starts_with($path, $pattern)) {
        $rest = substr($path, strlen($pattern));

        // Capture policymaker slug from the path.
        $policymaker = preg_match('/^\/([\p{Ll}\d-]+)/u', $rest, $matches) ? '/' . $matches[1] : '';

        // Return the English path that the browse controller expects.
        return '/decisionmakers/browse-decisionmakers' . $policymaker;
      }
    }
    catch (\UnhandledMatchError) {
    }

    // Return the original path.
    return $path;
  }

  /**
   * {@inheritDoc}
   */
  public function processOutbound($path, &$options = [], ?Request $request = NULL, ?BubbleableMetadata $bubbleable_metadata = NULL): string {
    if (preg_match('/\/decisionmakers\/browse-decisionmakers(\/[\p{Ll}0-9-]+)?$/u', $path, $matches)) {
      if (isset($options['language'])) {
        $langcode = $options['language'];

        if ($options['language'] instanceof LanguageInterface) {
          $langcode = $options['language']->getId();
        }

        try {
          $translated = match($langcode) {
            'fi' => '/paattajat/selaa-paattajia',
            'sv' => '/beslutsfattare/bladra-bland-beslutsfattare',
            // No need to alter the English path here.
          };

          return $translated . ($matches[1] ?? '');
        }
        catch (\UnhandledMatchError) {
        }
      }
    }

    return $path;
  }

}
