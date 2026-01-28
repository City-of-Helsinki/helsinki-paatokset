<?php

declare(strict_types=1);

namespace Drupal\paatokset_allu\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_api_base\Entity\Access\RemoteEntityAccess;
use Drupal\paatokset_allu\DocumentInterface;
use Drupal\paatokset_allu\Entity\Routing\EntityRouteProvider;
use Drupal\views\EntityViewsData;

/**
 * Defines the document entity class.
 */
#[ContentEntityType(
  id: 'paatokset_allu_document',
  label: new TranslatableMarkup('Document'),
  label_collection: new TranslatableMarkup('Documents'),
  label_singular: new TranslatableMarkup('document'),
  label_plural: new TranslatableMarkup('documents'),
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
    'uuid' => 'uuid',
  ],
  handlers: [
    'list_builder' => EntityListBuilder::class,
    'view_builder' => EntityViewBuilder::class,
    'views_data' => EntityViewsData::class,
    'access' => RemoteEntityAccess::class,
    'form' => [
      'default' => ContentEntityForm::class,
      'delete' => ContentEntityDeleteForm::class,
    ],
    'route_provider' => [
      'html' => EntityRouteProvider::class,
    ],
  ],
  links: [
    'collection' => '/admin/content/allu/document',
    'canonical' => '/allu/document/{paatokset_allu_document}/download',
    'edit-form' => '/allu/document/{paatokset_allu_document}/edit',
    'delete-form' => '/allu/document/{paatokset_allu_document}/delete',
  ],
  admin_permission: 'administer remote entities',
  base_table: 'paatokset_allu_document',
  label_count: [
    'singular' => '@count document',
    'plural' => '@count documents',
  ],
)]
class Document extends ContentEntityBase implements DocumentInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Label'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Authored on'))
      ->setDescription(new TranslatableMarkup('The time that the document was created.'));

    $fields['address'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Address'))
      ->setDescription(new TranslatableMarkup('Address that this document relates to.'));

    $fields['type'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Document type'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255);

    return $fields;
  }

}
