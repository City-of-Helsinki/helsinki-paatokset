<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\helfi_api_base\Entity\RemoteEntityInterface;

/**
 * Provides an interface defining an initiative entity type.
 */
interface InitiativeInterface extends ContentEntityInterface, RemoteEntityInterface {

}
