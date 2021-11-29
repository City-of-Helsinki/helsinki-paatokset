<?php

declare(strict_types = 1);

namespace Drupal\paatokset_ahjo_api\Plugin\media\Source;

use Drupal\media\MediaInterface;
use Drupal\media\MediaSourceBase;

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

  /**
   * {@inheritdoc}
   */
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

  /**
   * {@inheritdoc}
   */
  public function getMetadata(MediaInterface $media, $attribute_name) {
    // Get file attributes from JSON source field.
    $json_field = $media->get($this->configuration['source_field']);

    // Fallback if JSON field is empty.
    if (!$json_field || empty($json_field->value)) {
      return parent::getMetadata($media, $attribute_name);
    }

    $data = json_decode($json_field->value);

    switch ($attribute_name) {
      // Default name if left blank (through migrations for example).
      case 'default_name':
        return $data->Title;

      case 'title':
        return $data->Title;

      case 'type':
        return $data->Type;

      case 'id':
        return $data->NativeId;

      case 'uri':
        return $this->getAhjoFileUri($data->NativeId);

      case 'orig_uri':
        return $data->FileURI;

      case 'issued':
        $timestamp = $data->Issued;
        if (empty($timestamp)) {
          return NULL;
        }
        $datetime = new \DateTime("now", new \DateTimezone('UTC'));
        $datetime->setTimeStamp(strtotime($timestamp));
        return $datetime->format("Y-m-d\TH:i:s");

      case 'language':
        return $data->Language;

      case 'personaldata':
        return $data->PersonalData;

      default:
        return $data->$attribute_name ?? parent::getMetadata($media, $attribute_name);
    }
  }

  /**
   * Get local URI for external file.
   *
   * @param string $native_id
   *   Native ID of file.
   *
   * @return string
   *   Local URI through proxy to get past OpenID authentication.
   */
  private function getAhjoFileUri(string $native_id): string {
    return 'https://example.com/' . urlencode($native_id);
  }

}
