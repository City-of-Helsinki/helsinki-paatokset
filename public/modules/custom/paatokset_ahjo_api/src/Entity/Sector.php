<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Entity;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 *
 */
enum Sector: string {

  case EducationDivision = 'U420300';
  case UrbanEnvironmentDivision = 'U541000';
  case CentralAdministration = 'U50';
  case CultureAndLeisureDivision = 'U480400';
  /**
 * I think this division might be dissolved. */
  case SocialServicesAndHealthcareDivision = 'U320200';
  case SocialServicesHealthcareAndRescueServicesDivision = 'U321200';

  /**
   * Get translated sector label.
   */
  public function getLabel(): TranslatableMarkup {
    return match ($this) {
      self::EducationDivision => new TranslatableMarkup('Education Division', options: ['context' => 'Sector names']),
      self::UrbanEnvironmentDivision => new TranslatableMarkup('Urban Environment Division', options: ['context' => 'Sector names']),
      self::CentralAdministration => new TranslatableMarkup('Central Administration', options: ['context' => 'Sector names']),
      self::CultureAndLeisureDivision => new TranslatableMarkup('Culture and Leisure Division', options: ['context' => 'Sector names']),
      self::SocialServicesAndHealthcareDivision => new TranslatableMarkup('Social Services and Health Care Division', options: ['context' => 'Sector names']),
      self::SocialServicesHealthcareAndRescueServicesDivision => new TranslatableMarkup('Social Services, Health Care and Rescue Services Division', options: ['context' => 'Sector names']),
    };
  }

}
