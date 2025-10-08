<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\paatokset_ahjo_api\InitiativeInterface;

/**
 * Defines the initiative entity class.
 *
 * @ContentEntityType(
 *   id = "ahjo_initiative",
 *   label = @Translation("Initiative"),
 *   label_collection = @Translation("Initiatives"),
 *   label_singular = @Translation("initiative"),
 *   label_plural = @Translation("initiatives"),
 *   label_count = @PluralTranslation(
 *     singular = "@count initiatives",
 *     plural = "@count initiatives",
 *   ),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\Core\Entity\EntityListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\helfi_api_base\Entity\Access\RemoteEntityAccess",
 *     "form" = {
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "paatokset_ahjo_initiative",
 *   admin_permission = "administer remote entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "title",
 *   },
 *   links = {
 *     "collection" = "/admin/content/ahjo/initiative",
 *     "delete-form" = "/ahjo/initiative/{ahjo_initiative}/delete",
 *   },
 * )
 */
final class Initiative extends ContentEntityBase implements InitiativeInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Title'))
      ->setSetting('max_length', 255)
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'type' => 'string',
        'label' => 'hidden',
      ]);

    $fields['trustee_nid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Trustee'))
      ->setDescription(new TranslatableMarkup('Parent trustee.'))
      ->setRequired(TRUE)
      ->setSettings([
        'target_type' => 'node',
        'handler_settings' => [
          'target_bundles' => ['trustee' => 'trustee'],
        ],
      ]);

    $fields['date'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Created'))
      ->setDescription(new TranslatableMarkup('The timestamp that the initiative was created.'))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'timestamp',
      ]);

    $fields['uri'] = BaseFieldDefinition::create('uri')
      ->setLabel(new TranslatableMarkup('URI'))
      ->setDescription(new TranslatableMarkup('The URI to access the related document.'));

    return $fields;
  }

}
