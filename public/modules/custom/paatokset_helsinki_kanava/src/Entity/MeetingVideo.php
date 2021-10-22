<?php

declare(strict_types = 1);

namespace Drupal\paatokset_helsinki_kanava\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\RevisionLogEntityTrait;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Entity\ContentEntityBase;

/**
 * Defines meeting_video entity class.
 *
 * @ContentEntityType(
 *   id = "meeting_video",
 *   label = @Translation("Meeting video"),
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
 *   base_table = "paatokset_meeting_video",
 *   data_table = "paatokset_meeting_video_field_data",
 *   revision_table = "paatokset_meeting_video_revision",
 *   revision_data_table = "paatokset_meeting_video_field_revision",
 *   show_revision_ui = TRUE,
 *   translatable = TRUE,
 *   admin_permission = "administer remote entities",
 *   entity_keys = {
 *    "id" = "id",
 *    "revision" = "revision_id",
 *    "langcode" = "langcode",
 *    "uuid" = "uuid",
 *    "uid" = "uid",
 *    "label" = "label"
 *   },
 *   revision_metadata_keys = {
 *    "revision_created" = "revision_timestamp",
 *    "revision_user" = "revision_user",
 *    "revision_log_message" = "revision_log"
 *   },
 *   links = {
 *     "canonical" = "/meeting-video/{meeting_video}",
 *     "edit-form" = "/admin/content/integrations/meeting-video/{meeting_video}/edit",
 *     "delete-form" = "/admin/content/integrations/meeting-video/{meeting_video}/delete",
 *     "collection" = "/admin/content/integrations/meeting-video"
 *   },
 *   field_ui_base_route = "meeting_video.settings"
 * )
 */
final class MeetingVideo extends ContentEntityBase {
  use RevisionLogEntityTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('name'))
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

    $fields['embed_url'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('embed_url'))
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

    $fields['start_time'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Start time'))
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

    return $fields;
  }

}
