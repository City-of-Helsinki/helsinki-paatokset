<?php

namespace Drupal\paatokset_ahjo_api\Service;

use Drupal\paatokset_datapumppu\Entity\Statement;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\paatokset_datapumppu\Service\StatementService;

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
    if (isset($nameParts[1])) {
      return trim($nameParts[1] . ' ' . $nameParts[0]);
    }
    else {
      return $nameParts[0];
    }
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

      if ($title === 'Jäsen' || $title === 'Medlem') {
        return t('Councillor');
      }

      if ($title === 'Varajäsen' || $title === 'Ersättare') {
        return t('Deputy councillor');
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
    // If (!$trustee->hasField('field_trustee_datapumppu_id') || $trustee->get('field_trustee_datapumppu_id')->isEmpty()) {
    //  return NULL;
    // }.

    /** @var \Drupal\paatokset_datapumppu\Service\StatementService $statementService */
    $statementService = \Drupal::service(StatementService::class);
    $statements = $statementService->getStatementsOfTrustee($trustee);

    // Placeholder content for layout before API integration is implemented.
    $content = [
      'meeting' => 'Kaupunginvaltuuston kokous 2021/26',
      'speaking_turn' => '13. Kansanäänestysaloite Malmin lentokentän säilyttämisestä ilmailukäytössä (2:13)',
      'link' => '/',
    ];

    return array_map(static fn (Statement $statement) => [
      'meeting' => $statement->get('meeting_id')->value,
      'speaking_turn' => $statement->get('title')->value,
      'link' => $statement->get('video_url')->value,
    ], $statements);
  }

  /**
   * Get memberships and roles for trustee.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Trustee node.
   *
   * @return array
   *   Array of chairmanships and roles.
   */
  public static function getMemberships(NodeInterface $node): array {
    $chairmanships = [];
    if ($node->hasField('field_trustee_chairmanships') && !$node->get('field_trustee_chairmanships')->isEmpty()) {
      foreach ($node->get('field_trustee_chairmanships') as $json) {
        $data = json_decode($json->value, TRUE);
        $chairmanships[] = $data['Position'] . ', ' . $data['OrganizationName'];
      };
    };

    // Only get meetings for the last year.
    $date_limit = date('Y-m-d', strtotime('-1 year'));
    $name = self::getTrusteeName($node);
    $meeting_nids = \Drupal::entityQuery('node')
      ->condition('type', 'meeting')
      ->condition('field_meeting_composition', $name, 'CONTAINS')
      ->condition('field_meeting_date', $date_limit, '>=')
      ->sort('field_meeting_date', 'DESC')
      ->execute();

    $memberships = [];
    $organizations = [];
    foreach (Node::loadMultiple($meeting_nids) as $node) {
      if (!$node->hasField('field_meeting_dm_id') || $node->get('field_meeting_dm_id')->isEmpty()) {
        continue;
      }

      if (in_array($node->field_meeting_dm_id->value, $organizations)) {
        continue;
      }
      $organizations[] = $node->field_meeting_dm_id->value;
      $org_name = $node->field_meeting_dm->value;

      foreach ($node->field_meeting_composition as $field) {
        $data = json_decode($field->value, TRUE);
        if ($data['Name'] !== $name) {
          continue;
        }
        if ($data['Role'] === 'Puheenjohtaja') {
          continue;
        }
        $memberships[] = $data['Role'] . ', ' . $org_name;
      }
    }

    asort($memberships);
    return array_merge($chairmanships, $memberships);
  }

}
