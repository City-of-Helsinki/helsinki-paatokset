<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Url;

/**
 * Ahjo entity interface.
 */
interface AhjoEntityInterface extends ContentEntityInterface {

  /**
   * Gets ahjo proxy URL to this entity.
   */
  public function getProxyUrl(): Url;

  /**
   * Get field that is the field that Ahjo uses as an id field.
   *
   * Ahjo entities are stored as Drupal nodes, and the node
   * id is not related to the Ahjo id fields.
   */
  public function getAhjoId(): string;

}
