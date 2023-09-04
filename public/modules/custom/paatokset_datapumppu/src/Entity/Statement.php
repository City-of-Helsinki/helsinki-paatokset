<?php

declare(strict_types=1);

namespace Drupal\paatokset_datapumppu\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\RevisionLogEntityTrait;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines the paatokset_statement entity class.
 *
 * @ContentEntityType(
 *   id = "paatokset_statement",
 *   label = @Translation("Päätökset - Statement"),
 *   label_collection = @Translation("Päätökset - Statement"),
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
 *   base_table = "paatokset_statement",
 *   data_table = "paatokset_statement_field_data",
 *   revision_table = "paatokset_statement_revision",
 *   revision_data_table = "paatokset_statement_field_revision",
 *   show_revision_ui = TRUE,
 *   translatable = TRUE,
 *   admin_permission = "administer remote entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "revision_id",
 *     "langcode" = "langcode",
 *     "uid" = "uid",
 *     "label" = "title",
 *     "uuid" = "uuid"
 *   },
 *   revision_metadata_keys = {
 *     "revision_created" = "revision_timestamp",
 *     "revision_user" = "revision_user",
 *     "revision_log_message" = "revision_log"
 *   },
 *   links = {
 *     "canonical" = "/paatokset-statement/{paatokset_statement}",
 *     "edit-form" = "/admin/content/integrations/paatokset-statement/{paatokset_statement}/edit",
 *     "delete-form" = "/admin/content/integrations/paatokset-statement/{paatokset_statement}/delete",
 *     "collection" = "/admin/content/integrations/paatokset-statement"
 *   },
 *   field_ui_base_route = "paatokset_statement.settings"
 * )
 */
final class Statement extends ContentEntityBase {

  use RevisionLogEntityTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Title'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ]);

    $fields['speaker'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Speaker'))
      ->setSetting('target_type', 'node')
      ->setSetting('handler_settings', ['target_bundles' => ['trustee' => 'trustee']])
      ->setDisplayOptions('view', [
        'type' => 'entity_reference_entity_view',
        'label' => 'hidden',
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['speech_type'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Speech type'))
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['video_url'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Video link'))
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ]);

    $fields['start_time'] = BaseFieldDefinition::create('datetime')
      ->setLabel(new TranslatableMarkup('Start time'))
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['duration'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Duration (seconds)'))
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['case_number'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Case number'))
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['meeting_id'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Meeting ID'))
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ]);

    return $fields;
  }

}
