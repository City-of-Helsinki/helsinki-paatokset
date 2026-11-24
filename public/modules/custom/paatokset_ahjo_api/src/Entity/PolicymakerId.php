<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Entity;

/**
 * Ahjo IDs of policymakers that need special handling.
 */
enum PolicymakerId: string {

  case CityCouncil = '02900';
  case CityBoard = '00400';

}
