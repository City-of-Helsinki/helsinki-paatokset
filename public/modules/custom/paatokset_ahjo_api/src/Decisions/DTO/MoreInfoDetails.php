<?php

namespace Drupal\paatokset_ahjo_api\Decisions\DTO;

use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * More info details for decision.
 */
final readonly class MoreInfoDetails {

  /**
   * Phone number.
   */
  public ?string $phone;

  public function __construct(
    public string $name,
    public string $title,
    ?string $phone = NULL,
    public ?string $email = NULL,
  ) {
    // Add area code to City phone numbers.
    if ($phone && str_starts_with($phone, '310')) {
      $this->phone = '09 ' . $phone;
    }
    else {
      $this->phone = $phone;
    }
  }

  /**
   * Get phone number as `tel:` link.
   */
  public function getPhoneLink(): ?Link {
    if ($this->phone && strlen($this->phone) > 2) {
      // Drupal cannot handle phone numbers with 5 or less
      // characters: https://www.drupal.org/node/2575577.
      // This inserts dash (-) after the first digit. RFC 3966
      // defines the dash as a visual separator character, so it
      // will be removed before the phone number is used.
      $phone = str_replace(' ', '', $this->phone);
      if (strlen($phone) <= 5) {
        $phone = substr_replace($phone, '-', 1, 0);
      }

      try {
        return Link::fromTextAndUrl($this->phone, Url::fromUri("tel:$phone"));
      }
      catch (\InvalidArgumentException) {
      }
    }

    return NULL;
  }

  /**
   * Get email address as `mailto:` link.
   */
  public function getEmailLink(): ?Link {
    if ($this->email) {
      try {
        return Link::fromTextAndUrl($this->email, Url::fromUri('mailto:' . $this->email));
      }
      catch (\InvalidArgumentException) {
      }
    }

    return NULL;
  }

}
