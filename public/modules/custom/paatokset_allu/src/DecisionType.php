<?php

declare(strict_types=1);

namespace Drupal\paatokset_allu;

/**
 * Application decision type.
 */
enum DecisionType: string {

  case EXCAVATION_ANNOUNCEMENT = 'EXCAVATION_ANNOUNCEMENT';
  case AREA_RENTAL = 'AREA_RENTAL';
  case TEMPORARY_TRAFFIC_ARRANGEMENTS = 'TEMPORARY_TRAFFIC_ARRANGEMENTS';
  case PLACEMENT_CONTRACT = 'PLACEMENT_CONTRACT';
  case EVENT = 'EVENT';
  case SHORT_TERM_RENTAL = 'SHORT_TERM_RENTAL';

}
