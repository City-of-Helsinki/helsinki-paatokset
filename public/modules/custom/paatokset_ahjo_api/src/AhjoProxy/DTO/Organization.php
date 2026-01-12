<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\AhjoProxy\DTO;

use Drupal\Component\Utility\Unicode;
use Drupal\paatokset_ahjo_api\Entity\OrganizationType;

/**
 * Ahjo organization DTO.
 */
readonly class Organization {

  public function __construct(
    public string $id,
    public string $name,
    public bool $existing,
    public \DateTimeImmutable $formed,
    public \DateTimeImmutable $dissolved,
    public OrganizationType $type,
    public ?string $typeLabel = NULL,
  ) {
  }

  /**
   * Construct self from deserialized Ahjo response.
   *
   * @throws \ValueError
   * @throws \DateMalformedStringException
   */
  public static function fromAhjoObject(\stdClass $object): self {
    $name = $object->ID;

    if ($object->Name ?? FALSE) {
      $name = Unicode::truncate($object->Name, '255', TRUE, TRUE);
    }

    return new self(
      $object->ID,
      $name,
      filter_var($object->Existing, FILTER_VALIDATE_BOOLEAN),
      new \DateTimeImmutable($object->Formed),
      new \DateTimeImmutable($object->Dissolved),
      OrganizationType::tryFrom((int) $object->TypeId) ?? OrganizationType::UNKNOWN,
      $object->Type ?? NULL,
    );
  }

}
