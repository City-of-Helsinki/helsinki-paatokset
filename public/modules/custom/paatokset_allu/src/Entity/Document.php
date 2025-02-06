<?php

declare(strict_types=1);

namespace Drupal\paatokset_allu\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Url;
use Drupal\paatokset_allu\DocumentInterface;

/**
 * Allu document base class.
 */
abstract class Document extends ContentEntityBase implements DocumentInterface {

  /**
   * Route where allu PDF documents can be downloaded.
   */
  public const DOCUMENT_ROUTE = '';

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Label'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setDescription(t('The time that the document was created.'));

    $fields['type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Document type'))
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
