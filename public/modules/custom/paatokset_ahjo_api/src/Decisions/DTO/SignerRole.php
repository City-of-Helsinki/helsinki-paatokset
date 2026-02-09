<?php

namespace Drupal\paatokset_ahjo_api\Decisions\DTO;

/**
 * Enum for signer roles.
 */
enum SignerRole {

  case CHAIRMAN;
  case SECRETARY;

  /**
   * Selector that can be used to find a signer name from Ahjo HTML.
   */
  public function getNameSelector(): string {
    return match ($this) {
      self::CHAIRMAN => 'Puheenjohtajanimi',
      self::SECRETARY => 'Poytakirjanpitajanimi',
    };
  }

  /**
   * Selector that can be used to find a signer name from Ahjo HTML.
   */
  public function getRoleSelector(): string {
    return match ($this) {
      self::CHAIRMAN => 'Puheenjohtajaotsikko',
      self::SECRETARY => 'Poytakirjanpitajaotsikko',
    };
  }

}
