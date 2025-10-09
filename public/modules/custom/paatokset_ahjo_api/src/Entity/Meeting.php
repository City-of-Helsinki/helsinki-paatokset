<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Entity;

use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\paatokset_policymakers\Enum\PolicymakerRoutes;
use Drupal\paatokset_policymakers\Service\PolicymakerService;

/**
 * Bundle class for meetings.
 */
class Meeting extends Node implements AhjoUpdatableInterface {

  /**
   * {@inheritDoc}
   */
  public function getProxyUrl(): Url {
    $id = $this->getAhjoId();

    if (!$id) {
      throw new \InvalidArgumentException("Meeting is missing Ahjo ID");
    }

    return Url::fromRoute('paatokset_ahjo_proxy.meetings_single', [
      'id' => $this->getAhjoId(),
    ]);
  }

  /**
   * {@inheritDoc}
   */
  public function getAhjoId(): string {
    return $this->get('field_meeting_id')->getString();
  }

  /**
   * {@inheritDoc}
   */
  public static function getAhjoEndpoint(): string {
    return 'meetings';
  }

  /**
   * Get meeting minutes document from meeting node.
   *
   * @param string $document_type
   *   Document type, for example, 'esityslista', 'pöytäkirja'.
   * @param string|null $langcode
   *   Document language.
   *
   * @return array|null
   *   Meeting minutes JSON, if one exists for the meeting.
   */
  public function getDocumentFromEntity(string $document_type, ?string $langcode = NULL): ?array {
    foreach ($this->get('field_meeting_documents') as $field) {
      $document = json_decode($field->value, TRUE);

      if ($langcode && isset($document['Language']) && !str_contains($document['Language'], $langcode)) {
        continue;
      }

      if (isset($document['Type']) && isset($document['NativeId']) && $document['Type'] === $document_type) {
        return $document;
      }
    }

    return NULL;
  }

  /**
   * Get meeting phase enum from Ahjo document.
   */
  public function getMeetingPhase(): ?MeetingPhase {
    foreach ($this->get('field_meeting_documents') as $field) {
      $document = json_decode($field->value);

      if (empty($document)) {
        \Drupal::service('logger.channel.paatokset_ahjo_api')
          ->warning('Meeting document is empty for meeting id @meeting_id', [
            '@meeting_id' => $this->id(),
          ]);

        continue;
      }

      $phase = match ($document->Type) {
        'pöytäkirja' => MeetingPhase::MINUTES,
        'esityslista' => $this->get('field_meeting_decision')->isEmpty() ? MeetingPhase::AGENDA : MeetingPhase::DECISION,
        default => NULL,
      };

      if ($phase) {
        return $phase;
      }
    }

    return NULL;
  }

  /**
   * Gets related policymaker.
   */
  public function getPolicymaker(): ?Policymaker {
    $policymakerId = $this->get('field_meeting_dm_id')->value;

    // Load policymaker this meeting relates to
    // (Paatokset does not use reference fields).
    $entityStorage = \Drupal::entityTypeManager()->getStorage('node');

    return array_first($entityStorage->loadByProperties([
      'type' => 'policymaker',
      'field_policymaker_id' => $policymakerId,
    ]));
  }

  /**
   * Get minutes url from meeting.
   *
   * @param string|null $langcode
   *   Link translation. Defaults to current language.
   *
   * @todo This should use Drupal Links & Route Provider system.
   * Revisit this if these are converted to custom entities.
   * https://www.drupal.org/docs/drupal-apis/entity-api/introduction-to-entity-api-in-drupal-8#s-links-route-provider
   */
  public function getMinutesUrl(?string $langcode = NULL): ?Url {
    if (!$langcode) {
      $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
    }

    // Load policymaker this meeting relates to
    // (Paatokset does not use reference fields).
    $policymaker = $this->getPolicymaker();
    if (!$policymaker instanceof Policymaker || $policymaker->isTrustee()) {
      return NULL;
    }

    $routeOptions = [];

    return PolicymakerRoutes::getMinutesRoute(
      $langcode,
      [
        'organization' => $policymaker->getPolicymakerOrganizationFromUrl($langcode),
        'id' => $this->getAhjoId(),
      ],
      $routeOptions
    );
  }

  /**
   * Get decision announcement url from meeting.
   *
   * @param string|null $langcode
   *   Link translation. Defaults to current language.
   *
   * @todo This should use Drupal Links & Route Provider system.
   * Revisit this if these are converted to custom entities.
   * https://www.drupal.org/docs/drupal-apis/entity-api/introduction-to-entity-api-in-drupal-8#s-links-route-provider
   */
  public function getDecisionAnnouncementUrl(?string $langcode = NULL): ?Url {
    $url = $this->getMinutesUrl($langcode);

    if ($url) {
      $langcode = $langcode ?? $url->getOption('language')->getId();
      $url->setOption('fragment', PolicymakerService::decisionAnnouncementAnchor($langcode));
    }

    return $url;
  }

}
