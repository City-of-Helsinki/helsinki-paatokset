<?php

declare(strict_types = 1);

namespace Drupal\paatokset_helsinki_kanava\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\paatokset_helsinki_kanava\Entity\MeetingVideo;

/**
 * Field formatter to render service maps.
 *
 * @FieldFormatter(
 *   id = "meeting_video_embed",
 *   label = @Translation("Paatokset - Meeting video embed"),
 *   field_types = {
 *     "string"
 *   }
 * )
 */
final class MeetingVideoFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'iframe_title' => 'Meeting video',
      'link_title' => t('View all meeting recordings'),
      'target' => TRUE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);

    $elements['iframe_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Iframe title'),
      '#default_value' => $this->getSetting('iframe_title'),
    ];

    $elements['link_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link title'),
      '#default_value' => $this->getSetting('link_title'),
    ];

    $elements['target'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Open link in new window'),
      '#default_value' => $this->getSetting('target'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];

    $entity = $items->getEntity();

    if (!$entity instanceof MeetingVideo) {
      throw new \InvalidArgumentException('The "meeting_video_embed" field can only be used with meeting_video entities.');
    }

    $embedUrl = $entity->get('embed_url')->value;
    $id = $entity->get('id')->value;

    if ($embedUrl) {
      $element[] = [
        'iframe' => [
          '#type' => 'html_tag',
          '#tag' => 'iframe',
          '#value' => '',
          '#attributes' => [
            'src' => $embedUrl,
            'frameborder' => 0,
            'title' => $this->getSetting('iframe_title'),
            'scrolling' => 'no',
          ],
          '#prefix' => '<div class="latest-meeting">',
          '#suffix' => '</div>',
        ],
      ];
    }

    return $element;
  }

}
