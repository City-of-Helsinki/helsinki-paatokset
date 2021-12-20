<?php

declare(strict_types = 1);

namespace Drupal\paatokset_ahjo\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\RevisionLogEntityTrait;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_api_base\Entity\RemoteEntityBase;

/**
 * Defines the paatokset_policymaker entity class.
 *
 * @ContentEntityType(
 *   id = "paatokset_policymaker",
 *   label = @Translation("Päätökset - policymaker"),
 *   label_collection = @Translation("Päätökset - policymaker"),
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
 *   base_table = "paatokset_policymaker",
 *   data_table = "paatokset_policymaker_field_data",
 *   revision_table = "paatokset_policymaker_revision",
 *   revision_data_table = "paatokset_policymaker_field_revision",
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
 *     "canonical" = "/paatokset-policymaker/{paatokset_policymaker}",
 *     "edit-form" = "/admin/content/integrations/paatokset-policymaker/{paatokset_policymaker}/edit",
 *     "delete-form" = "/admin/content/integrations/paatokset-policymaker/{paatokset_policymaker}/delete",
 *     "collection" = "/admin/content/integrations/paatokset-policymaker"
 *   },
 *   field_ui_base_route = "paatokset_policymaker.settings"
 * )
 */
final class Policymaker extends RemoteEntityBase {

  use RevisionLogEntityTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['policymaker_name'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('policymaker_name'))
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

    $fields['department'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('department'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setDefaultValue('')
      ->setCardinality(1)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['organization_type'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('organization_type'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setDefaultValue('')
      ->setCardinality(1)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['resource_uri'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('resource_uri'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setDefaultValue('')
      ->setCardinality(1)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }

}
