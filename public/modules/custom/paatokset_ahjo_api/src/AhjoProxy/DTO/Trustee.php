<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\AhjoProxy\DTO;

/**
 * Ahjo trustee DTO.
 */
final readonly class Trustee {

  /**
   * Constructs a new instance.
   *
   * @param string $id
   *   Ahjo ID.
   * @param string $name
   *   Trustee name.
   * @param ?string $councilGroup
   *   Trustee council group.
   * @param \Drupal\paatokset_ahjo_api\AhjoProxy\DTO\AhjojulkaisuDocument[] $initiatives
   *   Initiatives made by the trustee.
   * @param \Drupal\paatokset_ahjo_api\AhjoProxy\DTO\AhjojulkaisuDocument[] $resolutions
   *   Resolutions made by the trustee.
   * @param \Drupal\paatokset_ahjo_api\AhjoProxy\DTO\Chairmanship[] $chairmanships
   *   Chairmanships in Ahjo organizations.
   */
  public function __construct(
    public string $id,
    public string $name,
    public ?string $councilGroup,
    public array $initiatives,
    public array $resolutions,
    public array $chairmanships,
  ) {}

}
