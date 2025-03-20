<?php

declare(strict_types=1);

namespace Drupal\paatokset_rss\DTO;

/**
 * A DTO representing Lupapiste item.
 */
final readonly class LupapisteItem {

  /**
   * Constructs a new object.
   */
  public function __construct(
    public string $title,
    public string $description,
    public string $link,
    public \DateTime $pubDate,
    public string $toimenpideteksti,
    public string $lupatunnus,
    public \DateTime $julkaisuAlkaa,
    public \DateTime $julkaisuPaattyy,
    public string $kiinteistotunnus,
    public \DateTime $paatosPvm,
    public string $paatoksenPykala,
    public string $paattaja,
    public string $asiakirjaLink,
  ) {
  }

}
