<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\migrate\source;

/**
 * Base class for organization sources.
 */
abstract class OrganizationSourceBase extends AhjoSourceBase {

  /**
   * Organization translations supported by Ahjo.
   */
  protected const array ALL_LANGCODES = ['fi', 'sv'];

  /**
   * {@inheritDoc}
   */
  public function fields(): array {
    return [
      'id' => 'Organization ID',
      'name' => 'Organization name',
      'existing' => 'If the organisation is not dissolved',
      'organization_above' => 'ID of the parent organization',
      'type' => 'Organization type ID',
      'langcode' => 'Langcode',
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function getIds(): array {
    return [
      'id' => [
        'type' => 'string',
      ],
      'langcode' => [
        'type' => 'string',
      ],
    ];
  }

}
