<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_api_base\Entity\RemoteEntityInterface;

/**
 * Defines the decision entity class.
 *
 * @ContentEntityType(
 *   id = "ahjo_organization",
 *   label = @Translation("Organization"),
 *   label_collection = @Translation("Organizations"),
 *   label_singular = @Translation("organization"),
 *   label_plural = @Translation("organizations"),
 *   label_count = @PluralTranslation(
 *     singular = "@count organization",
 *     plural = "@count organizations",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\Core\Entity\EntityListBuilder",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\helfi_api_base\Entity\Access\RemoteEntityAccess",
 *     "translation" = "Drupal\content_translation\ContentTranslationHandler",
 *     "form" = {
 *       "default" = "Drupal\Core\Entity\ContentEntityForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "paatokset_ahjo_organization",
 *   data_table = "paatokset_ahjo_organization_data",
 *   translatable = TRUE,
 *   admin_permission = "administer remote entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "title",
 *     "langcode" = "langcode",
 *     "published" = "existing",
 *   },
 *   links = {
 *     "collection" = "/admin/content/ahjo/organizations",
 *     "canonical" = "/ahjo/organization/{ahjo_organization}",
 *     "edit-form" = "/ahjo/organization/{ahjo_organization}/edit",
 *     "delete-form" = "/ahjo/organization/{ahjo_organization}/delete",
 *   },
 * )
 */
class Organization extends ContentEntityBase implements EntityPublishedInterface, EntityChangedInterface, RemoteEntityInterface {

  use EntityPublishedTrait;
  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    // Encode "Existing" field from Ahjo into entity published status.
    $fields += self::publishedBaseFieldDefinitions($entity_type);

    $fields[$entity_type->getKey('id')] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('ID'))
      ->setDescription(t('The organization ID.'))
      ->setReadOnly(TRUE)
      ->setSetting('is_ascii', TRUE);

    $fields[$entity_type->getKey('label')] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Name'))
      ->setRequired(TRUE)
      ->setTranslatable(TRUE)
      ->setSetting('max_length', 255);

    $fields['organization_above'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Organization level above'))
      ->setSettings([
        'target_type' => 'ahjo_organization',
      ])
      ->setDescription(new TranslatableMarkup('Parent organization.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(new TranslatableMarkup('Changed'))
      ->setDescription(new TranslatableMarkup('The time that this organization was last saved.'));

    return $fields;
  }

  /**
   * Get parent organization.
   */
  public function getParentOrganization(): ?Organization {
    $parent = $this->get('organization_above')->entity;
    assert($parent instanceof Organization);
    return $parent;
  }

}
