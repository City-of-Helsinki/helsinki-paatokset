<?php

declare(strict_types=1);

namespace Drupal\paatokset_allu\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\paatokset_allu\DocumentInterface;

/**
 * Defines the decision entity class.
 *
 * @ContentEntityType(
 *   id = "paatokset_allu_document",
 *   label = @Translation("Document"),
 *   label_collection = @Translation("Documents"),
 *   label_singular = @Translation("document"),
 *   label_plural = @Translation("documents"),
 *   label_count = @PluralTranslation(
 *     singular = "@count document",
 *     plural = "@count documents",
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
 *   base_table = "paatokset_allu_document",
 *   admin_permission = "administer remote entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "collection" = "/admin/content/allu/document",
 *     "canonical" = "/allu/document/{paatokset_allu_document}/download",
 *     "edit-form" = "/allu/document/{paatokset_allu_document}/edit",
 *     "delete-form" = "/allu/document/{paatokset_allu_document}/delete",
 *   },
 * )
 */
class Document extends ContentEntityBase implements DocumentInterface {

  /**
   * {@inheritdoc}
   */
  public const DOCUMENT_ROUTE = 'paatokset_allu.document';

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Label'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Authored on'))
      ->setDescription(new TranslatableMarkup('The time that the document was created.'));

    $fields['address'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Address'))
      ->setDescription(new TranslatableMarkup('Address that this document relates to.'));

    $fields['type'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Document type'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255);

    return $fields;
  }

  /**
   * {@inheritDoc}
   */
  public function getDocumentUrl(): Url {
    if (!static::DOCUMENT_ROUTE) {
      throw new \LogicException("Subclasses must override DOCUMENT_ROUTE constant");
    }

    return Url::fromRoute(static::DOCUMENT_ROUTE, [
      'document' => $this->id(),
    ]);
  }

}
