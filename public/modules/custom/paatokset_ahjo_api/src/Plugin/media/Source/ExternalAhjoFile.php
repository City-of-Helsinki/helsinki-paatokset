<?php

declare(strict_types = 1);

namespace Drupal\paatokset_ahjo_api\Plugin\media\Source;

use Drupal\media\MediaInterface;
use Drupal\media\MediaSourceBase;
use Drupal\json_field\Plugin\Field\FieldType\JSONItem;

/**
 * Source wrapping around an external document from Ahjo.
 *
 * @see \Drupal\file\FileInterface
 *
 * @MediaSource(
 *   id = "ahjo_file",
 *   label = @Translation("External file from Ahjo API"),
 *   description = @Translation("Document from Ahjo API."),
 *   allowed_field_types = {"json_native"},
 *   default_thumbnail_filename = "generic.png"
 * )
 */
class ExternalAhjoFile extends MediaSourceBase {

  public function getMetadataAttributes() {
    return [
      'title' => $this->t('Title'),
      'id' => $this->t('ID'),
      'uri' => $this->t('URL'),
      'orig_uri' => $this->t('Original File URI'),
      'type' => $this->t('Type'),
      'issued' => $this->t('Issued'),
      'personaldata' => $this->t('Personal Data'),
      'language' => $this->t('Language'),
    ];
  }

  public function getMetadata(MediaInterface $media, $attribute_name) {
    // Get file attributes from JSON source field.
    $json_field = $media->get($this->configuration['source_field']);
    $data = json_decode($json_field->value);

    // Fallback if JSON field is empty.
    if (!$json_field) {
      return parent::getMetadata($media, $attribute_name);
    }

    switch ($attribute_name) {
      // This is used to set the name of the media entity if the user leaves the field blank.
      case 'default_name':
        return $data->Title;

      case 'title':
        return $data->Title;

      case 'type':
        return $data->Type;

      case 'id':
        return $data->NativeId;

      case 'uri':
        return $this->getAhjoFileUri($data->NativeID);

      case 'orig_uri':
        return $this->FileURI;

      case 'issued':
        return $data->Issued;

      case 'language':
        return $data->Language;

      case 'personal_data':
        return $data->PersonalData;

      default:
        return $data->$attribute_name ?? parent::getMetadata($media, $attribute_name);
    }
  }


  private function getAhjoFileUri(string $native_id): string {
    return 'https://example.com/' . $native_id;
  }

}
