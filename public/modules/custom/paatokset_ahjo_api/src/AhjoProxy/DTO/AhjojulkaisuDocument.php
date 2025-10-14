<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\AhjoProxy\DTO;

/**
 * DTO for document from ahjojulkaisu.hel.fi.
 */
final readonly class AhjojulkaisuDocument {

  public function __construct(
    public string $title,
    public \DateTimeImmutable $date,
    public string $url,
  ) {}

  /**
   * Construct self from deserialized Ahjo response.
   */
  public static function fromAhjoObject(\stdClass $object): self {
    return new self(
      $object->Title,
      new \DateTimeImmutable($object->Date),
      $object->FileURI,
    );
  }

}
