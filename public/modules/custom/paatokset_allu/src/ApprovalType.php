<?php

declare(strict_types=1);

namespace Drupal\paatokset_allu;

/**
 * Application approval type.
 */
enum ApprovalType: string {
  case OPERATIONAL_CONDITION = 'OPERATIONAL_CONDITION';
  case WORK_FINISHED = 'WORK_FINISHED';
}
