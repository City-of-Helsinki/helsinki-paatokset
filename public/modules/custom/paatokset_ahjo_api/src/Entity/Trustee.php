<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Entity;

use Drupal\node\Entity\Node;

/**
 * A bundle class for trustee nodes.
 */
class Trustee extends Node {

  /**
   * Format trustee title to the format that Datapumppu expects.
   *
   * E.g. 'Arhinmäki, Paavo' -> 'Arhinmäki Paavo'.
   *
   * @return string
   *   The title transformed into name string
   */
  public function getDatapumppuName(): string {
    // It is not feasible to build trustee names that the Datapumppu API expects
    // from Ahjo data alone. If the field_datapumppu_id is set, use it so
    // the name guessing can be overwritten manually until a better solution is
    // found.
    if (!$this->get('field_trustee_datapumppu_id')->isEmpty()) {
      return $this->get('field_trustee_datapumppu_id')->getString();
    }

    return str_replace(',', '', $this->getTitle());
  }

}
