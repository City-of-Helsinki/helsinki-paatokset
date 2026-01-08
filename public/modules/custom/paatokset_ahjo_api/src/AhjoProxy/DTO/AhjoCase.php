<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\AhjoProxy\DTO;

/**
 * Ahjo case DTO.
 */
final readonly class AhjoCase {

  /**
   * Constructs a new instance.
   *
   * @param string $id
   *   Case ID (e.g., HEL-2025-007790).
   * @param string $caseIdLabel
   *   Case ID label (e.g., HEL 2025-007790).
   * @param string $title
   *   Case title.
   * @param \DateTimeImmutable $created
   *   Created timestamp.
   * @param \DateTimeImmutable $acquired
   *   Acquired timestamp.
   * @param string $classificationCode
   *   Classification code.
   * @param string $classificationTitle
   *   Classification title.
   * @param string $status
   *   Case status.
   * @param string $language
   *   Case language.
   * @param string $publicityClass
   *   Publicity class.
   * @param string[] $securityReasons
   *   Security reasons.
   * @param \Drupal\paatokset_ahjo_api\AhjoProxy\DTO\AhjoHandling[] $handlings
   *   Case handlings.
   * @param \Drupal\paatokset_ahjo_api\AhjoProxy\DTO\AhjoRecord[] $records
   *   Case records.
   */
  public function __construct(
    public string $id,
    public string $caseIdLabel,
    public string $title,
    public \DateTimeImmutable $created,
    public \DateTimeImmutable $acquired,
    public string $classificationCode,
    public string $classificationTitle,
    public string $status,
    public string $language,
    public string $publicityClass,
    public array $securityReasons,
    public array $handlings,
    public array $records,
  ) {}

  /**
   * Construct self from deserialized Ahjo response.
   *
   * @param \stdClass $object
   *   Ahjo case object.
   *
   * @return self
   *   AhjoCase instance.
   *
   * @throws \DateMalformedStringException
   *   If date strings cannot be parsed.
   */
  public static function fromAhjoObject(\stdClass $object): self {
    return new self(
      $object->CaseID,
      $object->CaseIDLabel,
      $object->Title,
      new \DateTimeImmutable($object->Created),
      new \DateTimeImmutable($object->Acquired),
      $object->ClassificationCode,
      $object->ClassificationTitle,
      $object->Status,
      $object->Language,
      $object->PublicityClass,
      $object->SecurityReasons ?? [],
      array_map(AhjoHandling::class . '::fromAhjoObject', $object->Handlings ?? []),
      array_map(AhjoRecord::class . '::fromAhjoObject', $object->Records ?? []),
    );
  }

}
