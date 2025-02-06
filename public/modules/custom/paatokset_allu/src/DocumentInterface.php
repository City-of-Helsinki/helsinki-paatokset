<?php

declare(strict_types=1);

namespace Drupal\paatokset_allu;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Url;

/**
 * Provides an interface defining a document entity type.
 */
interface DocumentInterface extends ContentEntityInterface {

  /**
   * Get PDF document URL.
   *
   * @return \Drupal\Core\Url
   *   Document URL.
   */
  public function getDocumentUrl(): Url;

}
