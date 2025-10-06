<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Entity;

use Drupal\Core\Url;
use Drupal\node\Entity\Node;

/**
 * Bundle class for meetings.
 */
class Meeting extends Node implements AhjoUpdatableInterface {

  /**
   * {@inheritDoc}
   */
  public function getProxyUrl(): Url {
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
   * Get meeting phase enum from Ahjo document.
   */
  public function getMeetingPhase(): ?MeetingPhase {
    foreach ($this->get('field_meeting_documents') as $field) {
      $document = json_decode($field->value);

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

}
