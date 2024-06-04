<?php

namespace Drupal\paatokset_ahjo_api\Service;

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
   * @param \Drupal\node\NodeInterface $trustee
   *   Trustee node.
   *
   * @return array|null
   *   Trustee's speaking turns from the API, if found.
   */
  public static function getSpeakingTurns(NodeInterface $trustee): ?array {
    $currentLanguage = \Drupal::languageManager()->getCurrentLanguage()->getId();

    /** @var \Drupal\paatokset_datapumppu\Service\StatementService $statementService */
    $statementService = \Drupal::service(StatementService::class);

    $statements = $statementService->getStatementsByTrustee($trustee);
    $content = [];

    foreach ($statements as $statement) {
      if ($statement->hasTranslation($currentLanguage)) {
        $statement = $statement->getTranslation($currentLanguage);
      }

      $statementYear = $statement->get('start_time')->date->format('Y');

      $content[$statementYear][] = [
        'speaking_turn' => $statementService->formatStatementTitle($statement),
        'link' => $statement->get('video_url')->getString(),
      ];
    }

    krsort($content);

    return $content;
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
    $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
    /** @var \Drupal\paatokset_policymakers\Service\PolicymakerService $policymakerService */
    $policymakerService = \Drupal::service('paatokset_policymakers');

    $chairmanships = [];
    if ($node->hasField('field_trustee_chairmanships') && !$node->get('field_trustee_chairmanships')->isEmpty()) {
      foreach ($node->get('field_trustee_chairmanships') as $json) {
        $data = json_decode($json->value, TRUE);
        $position = $policymakerService->getTranslationForRole($data['Position']);
        $chairmanships[] = $position . ', ' . $data['OrganizationName'];
      };
    };

    $id = $node->get('field_trustee_id')->value;
    $org_nids = \Drupal::entityQuery('node')
      ->accessCheck(TRUE)
      ->condition('type', 'policymaker')
      ->condition('field_policymaker_existing', 1)
      ->condition('field_meeting_composition', $id, 'CONTAINS')
      ->execute();

    $memberships = [];
    $organizations = [];

    foreach (Node::loadMultiple($org_nids) as $node) {
      if ($node->hasTranslation($langcode)) {
        $node = $node->getTranslation($langcode);
      }

      $org_name = $node->title->value;

      if (in_array($org_name, $organizations)) {
        continue;
      }

      $organizations[] = $org_name;

      foreach ($node->field_meeting_composition as $field) {
        $data = json_decode($field->value, TRUE);
        if ($data['ID'] !== $id) {
          continue;
        }
        if ($data['Role'] === 'Puheenjohtaja' || $data['Role'] === 'Varapuheenjohtaja') {
          continue;
        }

        $role = $policymakerService->getTranslationForRole($data['Role']);

        $memberships[] = $role . ', ' . $org_name;
      }
    }

    asort($memberships);
    return array_merge($chairmanships, $memberships);
  }

}
