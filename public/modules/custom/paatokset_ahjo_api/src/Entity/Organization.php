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
use Drupal\Core\Url;
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
class Organization extends ContentEntityBase implements EntityPublishedInterface, EntityChangedInterface, RemoteEntityInterface, AhjoEntityInterface {

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
   * Get child organizations.
   *
   * @return \Drupal\paatokset_ahjo_api\Entity\Organization[]
   *   Child organizations.
   */
  public function getChildOrganizations(): array {
    /** @var \Drupal\paatokset_ahjo_api\Entity\Organization[] $orgs */
    $orgs = \Drupal::entityTypeManager()
      ->getStorage('ahjo_organization')
      ->loadByProperties([
        'organization_above' => $this->id(),
        'existing' => 1,
      ]);
    return $orgs;
  }

  /**
   * Get parent organization.
   */
  public function getParentOrganization(): ?Organization {
    $parent = $this->get('organization_above')->entity;
    assert($parent instanceof Organization);
    return $parent;
  }

  /**
   * Get list of organization hierarchy from root up to the given organization.
   *
   * @returns \Drupal\paatokset_ahjo_api\Entity\Organization[]
   *   Organizations above this organization, including the current
   *   organization. Root organization is the first element.
   */
  public function getOrganizationHierarchy(): array {
    $hierarchy = [$this];
    $organization = $this;
    $langcode = $this->language()->getId();

    // Recursively load until the root organization is reached.
    while (!is_null($organization = $organization->getParentOrganization())) {
      $hierarchy[] = $organization->hasTranslation($langcode) ? $organization->getTranslation($langcode) : $organization;
    }

    return array_reverse($hierarchy);
  }

  /**
   * {@inheritDoc}
   */
  public function getProxyUrl(): Url {
    return Url::fromRoute('paatokset_ahjo_proxy.organization_single', [
      'id' => $this->getAhjoId(),
    ]);
  }

  /**
   * {@inheritDoc}
   */
  public function getAhjoId(): string {
    return $this->id();
  }

}
