<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Entity;

/**
 * Interface for confidential documents.
 *
 * Both cases and decisions can be confidential. Note that public cases
 * can still have confidential decisions.
 */
interface ConfidentialityInterface {

  /**
   * Returns true if this document is confidential.
   */
  public function isConfidential(): bool;

  /**
   * Gets the legal justification for confidentiality of this document.
   *
   * NULL is returned if no confidentiality justification is available,
   * or the document is not confidential.
   */
  public function getConfidentialityReason(): string|NULL;

}
