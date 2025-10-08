<?php

namespace Drupal\paatokset_policymakers\Enum;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Url;
use Drupal\paatokset_ahjo_api\Entity\OrganizationType;

/**
 * Enum class for policymaker routes.
 */
class PolicymakerRoutes {

  const array ORGANIZATION = [
    'documents' => 'policymaker.documents',
    'discussion_minutes' => 'policymaker.discussion_minutes',
  ];

  const array TRUSTEE = [
    'decisions' => 'policymaker.decisions',
  ];

  private function __construct() {}

  /**
   * Return all policymaker routes.
   */
  public static function getRoutes(string $langcode, OrganizationType $organizationType): array {
    if ($organizationType->isTrustee()) {
      $routes = self::TRUSTEE;
    }
    elseif ($organizationType === OrganizationType::COUNCIL) {
      // Only council type has `discussion_minutes` route.
      $routes = self::ORGANIZATION;
    }
    else {
      // Regular organization.
      $routes = array_diff_key(self::ORGANIZATION, array_flip(['discussion_minutes']));
    }

    return array_map(static fn ($route) => "$route.$langcode", $routes);
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
