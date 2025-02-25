<?php

namespace Drupal\paatokset_allu;

/**
 * Allu document type.
 */
enum DocumentType: string {

  case DECISION = 'DECISION';
  case APPROVAL = 'APPROVAL';

}
