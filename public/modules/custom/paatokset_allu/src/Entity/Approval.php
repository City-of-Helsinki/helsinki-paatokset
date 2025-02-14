<?php

declare(strict_types=1);

namespace Drupal\paatokset_allu\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\paatokset_allu\ApprovalType;
use Drupal\paatokset_allu\DocumentInterface;

/**
 * Defines the decision entity class.
 *
 * @ContentEntityType(
 *   id = "paatokset_allu_approval",
 *   label = @Translation("Approval"),
 *   label_collection = @Translation("Approval"),
 *   label_singular = @Translation("approval"),
 *   label_plural = @Translation("approvals"),
 *   label_count = @PluralTranslation(
 *     singular = "@count approval",
 *     plural = "@count approvals",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\Core\Entity\EntityListBuilder",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\helfi_api_base\Entity\Access\RemoteEntityAccess",
 *     "form" = {
 *       "default" = "Drupal\Core\Entity\ContentEntityForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\paatokset_allu\Entity\Routing\EntityRouteProvider",
 *     },
 *   },
 *   base_table = "paatokset_allu_approval",
 *   admin_permission = "administer remote entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "collection" = "/admin/content/allu/approval",
 *     "canonical" = "/allu/approval/{paatokset_allu_approval}/download",
 *     "edit-form" = "/allu/approval/{paatokset_allu_approval}/edit",
 *     "delete-form" = "/allu/approval/{paatokset_allu_approval}/delete",
 *   },
 * )
 */
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
