<?php

declare(strict_types = 1);

namespace Drupal\paatokset_ahjo\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\RevisionLogEntityTrait;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_api_base\Entity\RemoteEntityBase;

/**
 * Defines the paatokset_meeting_document entity class.
 *
 * @ContentEntityType(
 *   id = "paatokset_meeting_document",
 *   label = @Translation("Päätökset - Meeting Document"),
 *   label_collection = @Translation("Päätökset - Meeting Document"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\Core\Entity\EntityListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\helfi_api_base\Entity\Access\RemoteEntityAccess",
 *     "form" = {
 *       "default" = "Drupal\Core\Entity\ContentEntityForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\paatokset_ahjo\Entity\Routing\EntityRouteProvider",
 *     }
 *   },
 *   base_table = "paatokset_meeting_document",
 *   data_table = "paatokset_meeting_document_field_data",
 *   revision_table = "paatokset_meeting_document_revision",
 *   revision_data_table = "paatokset_meeting_document_field_revision",
 *   show_revision_ui = TRUE,
 *   translatable = TRUE,
 *   admin_permission = "administer remote entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "revision_id",
 *     "langcode" = "langcode",
 *      "uuid" = "uuid",
 *      "uid" = "uid"
 *   },
 *   revision_metadata_keys = {
 *     "revision_created" = "revision_timestamp",
 *     "revision_user" = "revision_user",
 *     "revision_log_message" = "revision_log"
 *   },
 *   links = {
 *     "canonical" = "/paatokset-meeting-document/{paatokset_meeting_document}",
 *     "edit-form" = "/admin/content/integrations/paatokset-meeting-document/{paatokset_meeting_document}/edit",
 *     "delete-form" = "/admin/content/integrations/paatokset-meeting-document/{paatokset_meeting_document}/delete",
 *     "collection" = "/admin/content/integrations/paatokset-meeting-document"
 *   },
 *   field_ui_base_route = "paatokset_meeting_document.settings"
 * )
 */
final class MeetingDocument extends RemoteEntityBase {

  use RevisionLogEntityTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['last_modified_time'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('last_modified_time'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setDefaultValue('')
      ->setCardinality(1)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ]);

    $fields['publish_time'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('publish_time'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setDefaultValue('')
      ->setCardinality(1)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['xml_uri'] = BaseFieldDefinition::create('uri')
      ->setLabel(new TranslatableMarkup('xml_uri'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setDefaultValue('')
      ->setCardinality(1)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['origin_url'] = BaseFieldDefinition::create('string_long')
      ->setLabel(new TranslatableMarkup('origin_url'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setDefaultValue('')
      ->setCardinality(1)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['meeting_id'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('meeting_id'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setDefaultValue('')
      ->setCardinality(1)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }

}
