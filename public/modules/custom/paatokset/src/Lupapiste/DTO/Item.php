<?php

declare(strict_types=1);

namespace Drupal\paatokset\Lupapiste\DTO;

/**
 * A DTO representing Lupapiste item.
 */
final readonly class Item {

  /**
   * Constructs a new object.
   *
   * NOTE: All values must provide a default value or be nullable, so
   * we can add new fields and keep this backwards-compatible with already
   * stored data.
   */
  public function __construct(
    public string $title = '',
    public string $description = '',
    public string $link = '',
    public ?\DateTime $pubDate = NULL,
    public string $toimenpideteksti = '',
    public string $rakennuspaikka = '',
    public string $lupatunnus = '',
    public ?\DateTime $julkaisuAlkaa = NULL,
    public ?\DateTime $julkaisuPaattyy = NULL,
    public string $kiinteistotunnus = '',
    public ?\DateTime $paatosPvm = NULL,
    public string $paatoksenPykala = '',
    public string $paattaja = '',
    public string $asiakirjaLink = '',
  ) {
  }

}
