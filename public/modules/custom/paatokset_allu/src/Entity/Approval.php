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
use Drupal\paatokset_allu\ApprovalType;
use Drupal\paatokset_allu\DocumentInterface;
use Drupal\paatokset_allu\Entity\Routing\EntityRouteProvider;
use Drupal\views\EntityViewsData;

/**
 * Defines the approval entity class.
 */
#[ContentEntityType(
  id: 'paatokset_allu_approval',
  label: new TranslatableMarkup('Approval'),
  label_collection: new TranslatableMarkup('Approval'),
  label_singular: new TranslatableMarkup('approval'),
  label_plural: new TranslatableMarkup('approvals'),
  entity_keys: [
    'id' => 'id',
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
    'collection' => '/admin/content/allu/approval',
    'canonical' => '/allu/approval/{paatokset_allu_approval}/download',
    'edit-form' => '/allu/approval/{paatokset_allu_approval}/edit',
    'delete-form' => '/allu/approval/{paatokset_allu_approval}/delete',
  ],
  admin_permission: 'administer remote entities',
  base_table: 'paatokset_allu_approval',
  label_count: [
    'singular' => '@count approval',
    'plural' => '@count approvals',
  ],
)]
class Approval extends ContentEntityBase implements DocumentInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Authored on'))
      ->setDescription(new TranslatableMarkup('The time that the document was created.'));

    $fields['type'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Document type'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255);

    $fields['document'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Document'))
      ->setSettings([
        'target_type' => 'paatokset_allu_document',
      ]);

    return $fields;
  }

  /**
   * Get referenced document.
   */
  public function getDocument(): ?Document {
    $entity = $this->get('document')?->entity;
    assert(!$entity || $entity instanceof Document);
    return $entity;
  }

  /**
   * Get approval type.
   */
  public function getApprovalType(): ?ApprovalType {
    return ApprovalType::tryFrom($this->get('type')->value);
  }

}
