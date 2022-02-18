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
  public static function getTrusteeName(NodeInterface $trustee) {
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
  public static function transformTrusteeName(string $title) {
    $nameParts = explode(',', $title);
    return $nameParts[1] . ' ' . $nameParts[0];
  }

  /**
   * Get the trustee title, with some cases transformed.
   *
   * @param Drupal\node\NodeInterface $trustee
   *   The trustee node.
   *
   * @return string|void
   *   The trustee title to display or void
   */
  public static function getTrusteeTitle(NodeInterface $trustee) {
    if ($title = $trustee->get('field_trustee_title')->value) {
      if ($title == 'Jäsen') {
        return t('Valtuutettu');
      }

      if ($title == 'Varajäsen') {
        return t('Varavaltuutettu');
      }

      return $title;
    }
  }

}
