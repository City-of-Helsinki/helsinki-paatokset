<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\AhjoProxy\DTO;

/**
 * Ahjo record DTO.
 */
final readonly class AhjoRecord {

  /**
   * Constructs a new instance.
   *
   * @param string $title
   *   Record title.
   * @param ?string $attachmentNumber
   *   Attachment number (can be null).
   * @param string $publicityClass
   *   Publicity class (e.g., "Julkinen").
   * @param ?string $securityReasons
   *   Security reasons (can be null).
   * @param string $versionSeriesId
   *   Version series ID.
   * @param string $nativeId
   *   Native ID.
   * @param ?string $type
   *   Record type (e.g., "esitys", "päätös", "aloite").
   * @param string $fileUri
   *   File URI/URL.
   * @param string $language
   *   Language code.
   * @param string $personalData
   *   Personal data information.
   * @param ?\DateTimeImmutable $issued
   *   Issued timestamp (null if empty).
   */
  public function __construct(
    public string $title,
    public ?string $attachmentNumber,
    public string $publicityClass,
    public ?string $securityReasons,
    public string $versionSeriesId,
    public string $nativeId,
    public ?string $type,
    public string $fileUri,
    public string $language,
    public string $personalData,
    public ?\DateTimeImmutable $issued,
  ) {}

  /**
   * Construct self from deserialized Ahjo response.
   *
   * @throws \DateMalformedStringException
   */
  public static function fromAhjoObject(\stdClass $object): self {
    $issued = NULL;
    if (!empty($object->Issued)) {
      $issued = new \DateTimeImmutable($object->Issued);
    }

    return new self(
      $object->Title,
      $object->AttachmentNumber,
      $object->PublicityClass,
      $object->SecurityReasons,
      $object->VersionSeriesId,
      $object->NativeId,
      $object->Type,
      $object->FileURI,
      $object->Language,
      $object->PersonalData,
      $issued,
    );
  }

}
