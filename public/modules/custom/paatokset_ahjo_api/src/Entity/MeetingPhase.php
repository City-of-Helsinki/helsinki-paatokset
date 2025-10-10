<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Entity;

/**
 * Meeting phase.
 */
enum MeetingPhase: string {
  case MINUTES = 'minutes';
  case AGENDA = 'agenda';
  case DECISION = 'decision';
}
