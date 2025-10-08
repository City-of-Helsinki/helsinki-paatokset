<?php

namespace Drupal\paatokset_policymakers\Enum;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Url;

/**
 * Enum class for policymaker routes.
 */
class PolicymakerRoutes {
  const ORGANIZATION = [
    'documents' => 'policymaker.documents',
    'discussion_minutes' => 'policymaker.discussion_minutes',
  ];

  const TRUSTEE = [
    'decisions' => 'policymaker.decisions',
  ];

  private function __construct() {}

  /**
   * Return all organization-specific routes.
   */
  public static function getOrganizationRoutes() {
    return self::ORGANIZATION;
  }

  /**
   * Return all trustee-specific routes.
   */
  public static function getTrusteeRoutes() {
    return self::TRUSTEE;
  }

  /**
   * Return minutes sub-route.
   */
  public static function getMinutesRoute(string $langcode, array $routeParams = [], array $options = []): Url {
    $language = \Drupal::languageManager()->getLanguage($langcode);

    if (!$language instanceof LanguageInterface) {
      throw new \InvalidArgumentException("Invalid language code: $langcode");
    }

    // Paatokset has complicated language handling. We want
    // to translate URLs for some controllers, so we define
    // a separate route for each language.
    // @todo revisit this in in UHF-11726.
    return Url::fromRoute(
      "policymaker.minutes.$langcode",
      $routeParams,
      // These "translated" URLs must have language set,
      // so the interface will be translated correctly.
      array_merge($options, [
        'language' => $language,
      ])
    );
  }

}
