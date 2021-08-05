<?php

declare(strict_types = 1);

namespace Drupal\paatokset_ahjo\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\RevisionLogEntityTrait;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_api_base\Entity\RemoteEntityBase;

/**
 * Defines the paatokset_agenda_item entity class.
 *
 * @ContentEntityType(
 *   id = "paatokset_agenda_item",
 *   label = @Translation("Päätökset - Adenga item"),
 *   label_collection = @Translation("Päätökset - Adenga item"),
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
 *       "html" = "Drupal\helfi_api_base\Entity\Routing\EntityRouteProvider",
 *     }
 *   },
 *   base_table = "paatokset_agenda_item",
 *   data_table = "paatokset_agenda_item_field_data",
 *   revision_table = "paatokset_agenda_item_revision",
 *   revision_data_table = "paatokset_agenda_item_field_revision",
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
 *     "canonical" = "/paatokset-agenda-item/{paatokset_agenda_item}",
 *     "edit-form" = "/admin/content/integrations/paatokset-agenda-item/{paatokset_agenda_item}/edit",
 *     "delete-form" = "/admin/content/integrations/paatokset-agenda-item/{paatokset_agenda_item}/delete",
 *     "collection" = "/admin/content/integrations/paatokset-agenda-item"
 *   },
 *   field_ui_base_route = "paatokset_agenda_item.settings"
 * )
 */
final class AgendaItem extends RemoteEntityBase {

  use RevisionLogEntityTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['file_name'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('file_name'))
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

    $fields['file_url'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('file_url'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setDefaultValue('')
      ->setCardinality(1)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['classification_description'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('classification_description'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setDefaultValue('')
      ->setCardinality(1)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['classification_code'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('classification_code'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setDefaultValue('')
      ->setCardinality(1)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);
    $fields['content_type'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('classification_code'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setDefaultValue('')
      ->setCardinality(1)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);
    $fields['content_text'] = BaseFieldDefinition::create('string_long')
      ->setLabel(new TranslatableMarkup('classification_code'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setDefaultValue('')
      ->setCardinality(1)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);
    $fields['introducer'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('introducer'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setDefaultValue('')
      ->setCardinality(1)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);
    $fields['subject'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('subject'))
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
    $fields['meeting_policymaker_link'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('meeting_policymaker_link'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setDefaultValue('')
      ->setCardinality(1)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);
    $fields['meeting_date'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('meeting_date'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setDefaultValue('')
      ->setCardinality(1)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);
    $fields['meeting_number'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('meeting_number'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setDefaultValue('')
      ->setCardinality(1)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);
    $fields['subject_resolution'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('subject_resolution'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setDefaultValue('')
      ->setCardinality(1)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);
    $fields['origin_last_modifed_time'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('preparer'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setDefaultValue('')
      ->setCardinality(1)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);
    $fields['content_resolution'] = BaseFieldDefinition::create('text_long')
      ->setLabel(new TranslatableMarkup('content_resolution'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setDefaultValue('')
      ->setCardinality(1)
      ->setDisplayOptions('form', [
        'type'   => 'text_textarea',
        'weight' => -6,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);
    $fields['last_modifed_time'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('preparer'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setDefaultValue('')
      ->setCardinality(1)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);
    $fields['issue_id'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('issue_id'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setDefaultValue('')
      ->setCardinality(1)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);
    $fields['resource_uri'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('preparer'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setDefaultValue('')
      ->setCardinality(1)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);
    $fields['content_draft_proposal'] = BaseFieldDefinition::create('text_long')
      ->setLabel(new TranslatableMarkup('content_draft_proposal'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setDefaultValue('')
      ->setCardinality(1)
      ->setDisplayOptions('form', [
        'type'   => 'text_textarea',
        'weight' => -6,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);
    $fields['preparer'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('preparer'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setDefaultValue('')
      ->setCardinality(1)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);
    $fields['content_presenter'] = BaseFieldDefinition::create('text_long')
      ->setLabel(new TranslatableMarkup('content_presenter'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setDefaultValue('')
      ->setCardinality(1)
      ->setDisplayOptions('form', [
        'type'   => 'text_textarea',
        'weight' => -6,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);
    $fields['preparer'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('preparer'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setDefaultValue('')
      ->setCardinality(1)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);
    $fields['from_minutes'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('from_minutes'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setDefaultValue('')
      ->setCardinality(1)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);
    $fields['index'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('preparer'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setDefaultValue('')
      ->setCardinality(1)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);
    $fields['top_category_name'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('top_category_name'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setDefaultValue('')
      ->setCardinality(1)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);
    $fields['issue_subject'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('issue_subject'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setDefaultValue('')
      ->setCardinality(1)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }

}
