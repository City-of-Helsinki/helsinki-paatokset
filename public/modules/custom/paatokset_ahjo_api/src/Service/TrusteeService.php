<?php

namespace Drupal\paatokset_ahjo_api\Service;

use Drupal\node\NodeInterface;

/**
 * Service class for retrieving trustee-related data.
 *
 * @package Drupal\paatokset_ahjo_api\Services
 */
class TrusteeService {

  /**
   * Get trustee name in conventional name spelling.
   *
   * @param Drupal\node\NodeInterface $trustee
   *   The trustee node.
   *
   * @return string
   *   The title transformed into name string
   */
  public static function getTrusteeName(NodeInterface $trustee): string {
    return self::transformTrusteeName($trustee->getTitle());
  }

  /**
   * Format trustee title into conventional name spelling.
   *
   * Eg. 'Vapaavuori, Jan' -> 'Jan Vapaavuori'.
   *
   * @param string $title
   *   The title to be transformed.
   *
   * @return string
   *   The title transformed into name string
   */
  public static function transformTrusteeName(string $title): string {
    $nameParts = explode(',', $title);
    return $nameParts[1] . ' ' . $nameParts[0];
  }

  /**
   * Get the trustee title, with some cases transformed.
   *
   * @param Drupal\node\NodeInterface $trustee
   *   The trustee node.
   *
   * @return string|null
   *   The trustee title to display or NULL
   */
  public static function getTrusteeTitle(NodeInterface $trustee): ?string {
    if (!$trustee->hasField('field_trustee_title') || $trustee->get('field_trustee_title')->isEmpty()) {
      return NULL;
    }

    if ($title = $trustee->get('field_trustee_title')->value) {

      if ($title === 'Jäsen') {
        return t('Valtuutettu');
      }

      if ($title === 'Varajäsen') {
        return t('Varavaltuutettu');
      }

      return $title;
    }
  }

  /**
   * Get trustee speaking turns from Datapumppu integration.
   *
   * Note: Not implemented yet!
   *
   * @param Drupal\node\NodeInterface $trustee
   *   Trustee node.
   *
   * @return array|null
   *   Trustee's speaking turns from the API, if found.
   */
  public static function getSpeakingTurns(NodeInterface $trustee): ?array {
    if (!$trustee->hasField('field_trustee_datapumppu_id') || $trustee->get('field_trustee_datapumppu_id')->isEmpty()) {
      return NULL;
    }

    // Placeholder content for layout before API integration is implemented.
    $content = [
      'meeting' => 'Kaupunginvaltuuston kokous 2021/26',
      'speaking_turn' => '13. Kansanäänestysaloite Malmin lentokentän säilyttämisestä ilmailukäytössä (2:13)',
      'link' => '/',
    ];

    return [$content];
  }

}
