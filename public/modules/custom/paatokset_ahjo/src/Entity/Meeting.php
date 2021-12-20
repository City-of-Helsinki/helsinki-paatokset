<?php

declare(strict_types = 1);

namespace Drupal\paatokset_ahjo\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\RevisionLogEntityTrait;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_api_base\Entity\RemoteEntityBase;

/**
 * Defines the paatokset_meeting entity class.
 *
 * @ContentEntityType(
 *   id = "paatokset_meeting",
 *   label = @Translation("Päätökset - Meeting"),
 *   label_collection = @Translation("Päätökset - Meeting"),
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
 *   base_table = "paatokset_meeting",
 *   data_table = "paatokset_meeting_field_data",
 *   revision_table = "paatokset_meeting_revision",
 *   revision_data_table = "paatokset_meeting_field_revision",
 *   show_revision_ui = TRUE,
 *   translatable = TRUE,
 *   admin_permission = "administer remote entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "revision_id",
 *     "langcode" = "langcode",
 *      "uuid" = "uuid",
 *      "uid" = "uid",
 *      "label" = "display_name"
 *   },
 *   revision_metadata_keys = {
 *     "revision_created" = "revision_timestamp",
 *     "revision_user" = "revision_user",
 *     "revision_log_message" = "revision_log"
 *   },
 *   links = {
 *     "canonical" = "/paatokset-meeting/{paatokset_meeting}",
 *     "edit-form" = "/admin/content/integrations/paatokset-meeting/{paatokset_meeting}/edit",
 *     "delete-form" = "/admin/content/integrations/paatokset-meeting/{paatokset_meeting}/delete",
 *     "collection" = "/admin/content/integrations/paatokset-meeting"
 *   },
 *   field_ui_base_route = "paatokset_meeting.settings"
 * )
 */
final class Meeting extends RemoteEntityBase {

  use RevisionLogEntityTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['display_name'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Display name'))
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

    $fields['policymaker'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('policymaker'))
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

    $fields['meeting_date'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('meeting_date'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setDefaultValue('')
      ->setCardinality(1)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['policymaker_uri'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('policymaker_uri'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setDefaultValue('')
      ->setCardinality(1)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['number'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('number'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setDefaultValue('')
      ->setCardinality(1)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }

}
