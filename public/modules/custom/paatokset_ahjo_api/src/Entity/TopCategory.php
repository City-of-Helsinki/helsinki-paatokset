<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Entity;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Enum for Ahjo case top-level categories.
 *
 * The Ahjo API provides case data with a `ClassificationCode` field, which
 * is stored in the `classification_code` field of ahjo_case entities.
 *
 * Classification codes follow a hierarchical format (e.g., "00 01 02"), where
 * the first two digits represent the top-level category. This enum maps those
 * top-level category codes to their human-readable labels for display in the
 * UI.
 *
 * Example: A classification code of "05 03 01" would map to the SocialServices
 * category (code "05").
 */
enum TopCategory: string {

  case AdministrativeMatters = '00';
  case PersonnelMatters = '01';
  case FinancialMattersTaxationAndPropertyManagement = '02';
  case LegislationAndApplicationOfLegislation = '03';
  case InternationalActivitiesAndImmigrationPolicy = '04';
  case SocialServices = '05';
  case Healthcare = '06';
  case InformationManagement = '07';
  case Traffic = '08';
  case SecurityAndPublicOrder = '09';
  case LandUseConstructionAndHousing = '10';
  case EnvironmentalMatters = '11';
  case EducationAndCulture = '12';
  case ResearchAndDevelopment = '13';
  case BusinessAndEmploymentServices = '14';

  /**
   * Returns the top code label.
   */
  public function getLabel(): TranslatableMarkup {
    return match ($this) {
      self::AdministrativeMatters => new TranslatableMarkup('Administrative matters', options: ['context' => 'Ahjo cases']),
      self::PersonnelMatters => new TranslatableMarkup('Personnel matters', options: ['context' => 'Ahjo cases']),
      self::FinancialMattersTaxationAndPropertyManagement => new TranslatableMarkup('Financial matters, taxation and property management', options: ['context' => 'Ahjo cases']),
      self::LegislationAndApplicationOfLegislation => new TranslatableMarkup('Legislation and application of legislation', options: ['context' => 'Ahjo cases']),
      self::InternationalActivitiesAndImmigrationPolicy => new TranslatableMarkup('International activities and immigration policy', options: ['context' => 'Ahjo cases']),
      self::SocialServices => new TranslatableMarkup('Social services', options: ['context' => 'Ahjo cases']),
      self::Healthcare => new TranslatableMarkup('Healthcare', options: ['context' => 'Ahjo cases']),
      self::InformationManagement => new TranslatableMarkup('Information management', options: ['context' => 'Ahjo cases']),
      self::Traffic => new TranslatableMarkup('Traffic', options: ['context' => 'Ahjo cases']),
      self::SecurityAndPublicOrder => new TranslatableMarkup('Security and public order', options: ['context' => 'Ahjo cases']),
      self::LandUseConstructionAndHousing => new TranslatableMarkup('Land use, construction and housing', options: ['context' => 'Ahjo cases']),
      self::EnvironmentalMatters => new TranslatableMarkup('Environmental matters', options: ['context' => 'Ahjo cases']),
      self::EducationAndCulture => new TranslatableMarkup('Education and culture', options: ['context' => 'Ahjo cases']),
      self::ResearchAndDevelopment => new TranslatableMarkup('Research and development', options: ['context' => 'Ahjo cases']),
      self::BusinessAndEmploymentServices => new TranslatableMarkup('Business and employment services', options: ['context' => 'Ahjo cases']),
    };
  }

}
