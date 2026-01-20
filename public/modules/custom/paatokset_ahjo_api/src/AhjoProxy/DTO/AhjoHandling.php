<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\AhjoProxy\DTO;

/**
 * Ahjo handling DTO.
 */
final readonly class AhjoHandling {

  /**
   * Constructs a new instance.
   *
   * @param int $workSequenceNumber
   *   Work sequence number.
   * @param ?string $sector
   *   Sector name (e.g., "Keskushallinto").
   * @param string $status
   *   Handling status (e.g., "Päätöksenteossa", "Päättynyt").
   * @param \DateTimeImmutable $created
   *   Created timestamp.
   * @param string $dateNearestDeadline
   *   Date nearest deadline (can be empty string).
   * @param ?string $sectorId
   *   Sector ID (e.g., "U50").
   * @param array $links
   *   Links array.
   */
  public function __construct(
    public int $workSequenceNumber,
    public ?string $sector,
    public string $status,
    public \DateTimeImmutable $created,
    public string $dateNearestDeadline,
    public ?string $sectorId,
    public array $links,
  ) {}

  /**
   * Construct self from deserialized Ahjo response.
   *
   * @throws \DateMalformedStringException
   */
  public static function fromAhjoObject(\stdClass $object): self {
    return new self(
      $object->WorkSequenceNumber,
      $object->Sector,
      $object->Status,
      new \DateTimeImmutable($object->Created),
      $object->DateNearestDeadline,
      $object->SectorID,
      $object->links ?? [],
    );
  }

}
