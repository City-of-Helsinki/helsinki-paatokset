<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Entity;

/**
 * Ahjo updatable entity interface.
 *
 * Ahjo entities that implement this interface support
 * ahjo-proxy:update-entity drush command.
 */
interface AhjoUpdatableInterface extends AhjoEntityInterface {

  /**
   * Get Ahjo endpoint.
   *
   * Ahjo endpoint is part of the custom Url
   * scheme that the ahjo proxy system uses.
   *
   * For some entity types, this can vary by current interface language.
   *
   * @see \Drupal\paatokset_ahjo_proxy\AhjoProxy::migrateSingleEntity()
   */
  public static function getAhjoEndpoint(): string;

}
