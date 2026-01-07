<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\helfi_api_base\Entity\Access\RemoteEntityAccess;
use Drupal\paatokset_ahjo_api\InitiativeInterface;
use Drupal\views\EntityViewsData;

/**
 * Defines the initiative entity class.
 */
#[ContentEntityType(
  id: 'ahjo_initiative',
  label: new TranslatableMarkup('Initiative'),
  label_collection: new TranslatableMarkup('Initiatives'),
  label_singular: new TranslatableMarkup('initiative'),
  label_plural: new TranslatableMarkup('initiatives'),
  entity_keys: [
    'id' => 'id',
    'label' => 'title',
  ],
  handlers: [
    'view_builder' => EntityViewBuilder::class,
    'list_builder' => EntityListBuilder::class,
    'views_data' => EntityViewsData::class,
    'access' => RemoteEntityAccess::class,
    'form' => [
      'delete' => ContentEntityDeleteForm::class,
    ],
    'route_provider' => [
      'html' => AdminHtmlRouteProvider::class,
    ],
  ],
  links: [
    'collection' => '/admin/content/ahjo/initiative',
    'delete-form' => '/ahjo/initiative/{ahjo_initiative}/delete',
  ],
  admin_permission: 'administer remote entities',
  base_table: 'paatokset_ahjo_initiative',
  label_count: [
    'singular' => '@count initiatives',
    'plural' => '@count initiatives',
  ],
)]
final class Initiative extends ContentEntityBase implements InitiativeInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Title'))
      ->setSetting('max_length', 512)
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

  /**
   * {@inheritDoc}
   */
  public function getDocumentLink(): Link {
    return Link::fromTextAndUrl($this->label(), Url::fromUri($this->get('uri')->value));
  }

}
