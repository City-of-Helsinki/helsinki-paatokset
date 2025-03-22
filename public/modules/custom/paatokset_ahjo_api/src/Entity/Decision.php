<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Entity;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\Entity\Node;

/**
 * Bundle class for decisions.
 */
class Decision extends Node {

  /**
   * Get diary (case) number.
   */
  public function getDiaryNumber(): string {
    return $this->get('field_diary_number')->getString();
  }

  /**
   * Get decision attachments.
   *
   * @return array
   *   Array of links.
   */
  public function getAttachments(): array {
    $attachments = [];
    foreach ($this->get('field_decision_attachments') as $field) {
      $data = json_decode($field->value, TRUE);

      $number = NULL;
      if (isset($data['AttachmentNumber'])) {
        $number = $data['AttachmentNumber'] . '. ';
      }

      $title = $data['Title'] ?? NULL;
      $publicity_class = $data['PublicityClass'] ?? NULL;
      $file_url = $data['FileURI'] ?? NULL;

      // If all relevant info is empty, do not display attachment.
      if (empty($publicity_class) && empty($title) && empty($file_url)) {
        $title = new TranslatableMarkup("There's an error with this attachment. We are resolving the issue as soon as possible.");
        $publicity_class = 'error';
      }
      // Override title if attachment is not public.
      elseif ($publicity_class !== 'Julkinen') {
        if (!empty($data['SecurityReasons'])) {
          $title = new TranslatableMarkup('Confidential: @reasons', [
            '@reasons' => implode(', ', $data['SecurityReasons']),
          ]);
        }
        else {
          $title = new TranslatableMarkup('Confidential');
        }
      }

      $attachments[] = [
        'number' => $number,
        'file_url' => $file_url,
        'title' => $title,
        'publicity_class' => $publicity_class,
      ];
    }

    $publicity_reason = \Drupal::config('paatokset_ahjo_api.default_texts')->get('non_public_attachments_text.value');
    if (!empty($attachments)) {
      return [
        'items' => $attachments,
        'publicity_reason' => ['#markup' => $publicity_reason],
      ];
    }

    return [];
  }

}
