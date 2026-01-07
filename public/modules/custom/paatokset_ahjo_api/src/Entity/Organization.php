<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Entity;

use Drupal\content_translation\ContentTranslationHandler;
use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\helfi_api_base\Entity\Access\RemoteEntityAccess;
use Drupal\helfi_api_base\Entity\RemoteEntityBase;
use Drupal\views\EntityViewsData;

/**
 * Defines the organization entity class.
 */
#[ContentEntityType(
  id: 'ahjo_organization',
  label: new TranslatableMarkup('Organization'),
  label_collection: new TranslatableMarkup('Organizations'),
  label_singular: new TranslatableMarkup('organization'),
  label_plural: new TranslatableMarkup('organizations'),
  entity_keys: [
    'id' => 'id',
    'label' => 'title',
    'langcode' => 'langcode',
    'published' => 'existing',
  ],
  handlers: [
    'list_builder' => EntityListBuilder::class,
    'view_builder' => EntityViewBuilder::class,
    'views_data' => EntityViewsData::class,
    'access' => RemoteEntityAccess::class,
    'translation' => ContentTranslationHandler::class,
    'form' => [
      'default' => ContentEntityForm::class,
      'delete' => ContentEntityDeleteForm::class,
    ],
    'route_provider' => [
      'html' => AdminHtmlRouteProvider::class,
    ],
  ],
  links: [
    'collection' => '/admin/content/ahjo/organizations',
    'canonical' => '/ahjo/organization/{ahjo_organization}',
    'edit-form' => '/ahjo/organization/{ahjo_organization}/edit',
    'delete-form' => '/ahjo/organization/{ahjo_organization}/delete',
  ],
  admin_permission: 'administer remote entities',
  base_table: 'paatokset_ahjo_organization',
  data_table: 'paatokset_ahjo_organization_data',
  translatable: TRUE,
  label_count: [
    'singular' => '@count organization',
    'plural' => '@count organizations',
  ],
)]
class Organization extends RemoteEntityBase implements EntityPublishedInterface, EntityChangedInterface, AhjoEntityInterface {

  use EntityPublishedTrait;
  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public const MAX_SYNC_ATTEMPTS = 0;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    // Field from RemoteEntityBase we don't need.
    unset($fields['changed']);

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

    $fields['type'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Type ID'))
      ->setRequired(TRUE)
      ->setReadOnly(TRUE)
      ->setTranslatable(FALSE);

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

  /**
   * Gets the organization type.
   */
  public function getOranizationType(): OrganizationType {
    return OrganizationType::tryFrom($this->get('type')->value) ?? OrganizationType::UNKNOWN;
  }

  /**
   * Returns false if this organization is dissolved.
   */
  public function existing(): bool {
    return boolval($this->get('existing')->value);
  }

}
