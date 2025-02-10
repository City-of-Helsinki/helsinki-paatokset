<?php

declare(strict_types=1);

namespace Drupal\paatokset_allu;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\helfi_api_base\Entity\RemoteEntityInterface;

/**
 * Provides an interface defining a document entity type.
 */
interface DocumentInterface extends ContentEntityInterface, RemoteEntityInterface {
}
