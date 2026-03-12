<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\AhjoAdmin\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Settings form for Päätökset default settings.
 */
final class DefaultTextSettingsForm extends ConfigFormBase {

  /**
   * Config settings.
   */
  private const string SETTINGS = 'paatokset_ahjo_api.default_texts';

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'paatokset_default_text_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames(): array {
    return [
      self::SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['links'] = [
      '#type' => 'details',
      '#title' => $this->t('Links and URLs'),
      '#open' => TRUE,
    ];

    $form['links']['committees_boards_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Committees and boards URL'),
      '#description' => $this->t('Used on the decision tree page.'),
      '#config_target' => 'paatokset_ahjo_api.default_texts:committees_boards_url',
    ];

    $form['links']['office_holders_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Office holders URL'),
      '#description' => $this->t('Used on the decision tree page.'),
      '#config_target' => 'paatokset_ahjo_api.default_texts:office_holders_url',
    ];

    $form['alerts'] = [
      '#type' => 'details',
      '#title' => $this->t('Messages and alerts'),
      '#open' => TRUE,
    ];

    $form['defaults'] = [
      '#type' => 'details',
      '#title' => $this->t('Default fields'),
      '#open' => TRUE,
    ];

    $form['defaults']['calendar_notice_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Frontpage calendar notice text'),
      '#config_target' => 'paatokset_ahjo_api.default_texts:calendar_notice_text',
    ];

    $form['search'] = [
      '#type' => 'details',
      '#title' => $this->t('Search texts'),
      '#open' => TRUE,
    ];

    $form['banner'] = [
      '#type' => 'details',
      '#title' => $this->t('Decision banner'),
      '#open' => TRUE,
    ];

    $form['banner']['banner_heading'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Banner heading'),
      '#config_target' => 'paatokset_ahjo_api.default_texts:banner_heading',
    ];

    $form_defaults = [
      'alerts' => [
        'hidden_decisions_text' => $this->t('Hidden decisions text'),
        'non_public_attachments_text' => $this->t('Non public decision attachments text'),
      ],
      'defaults' => [
        'documents_description' => $this->t('Documents description'),
        'meetings_description' => $this->t('Meetings description'),
        'recording_description' => $this->t('Recording description'),
        'decisions_description' => $this->t('Decisions description'),
        'meeting_calendar_description' => $this->t('Meeting calendar description'),
      ],
      'search' => [
        'decision_search_description' => $this->t('Decision search description'),
        'policymakers_search_description' => $this->t('Policy makers search description'),
      ],
      'banner' => [
        'banner_text' => $this->t('Banner content'),
      ],
    ];

    $config = $this->config(self::SETTINGS);
    foreach ($form_defaults as $section => $defaults) {
      foreach ($defaults as $key => $title) {
        $form[$section][$key] = [
          '#type' => 'text_format',
          '#title' => $title,
          '#format' => $config->get($key . '.format'),
          '#default_value' => $config->get($key . '.value'),
        ];
      }
    }

    $form['banner']['banner_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('CTA button label'),
      '#config_target' => 'paatokset_ahjo_api.default_texts:banner_label',
    ];

    $form['banner']['banner_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('CTA button link'),
      '#config_target' => 'paatokset_ahjo_api.default_texts:banner_url',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    parent::submitForm($form, $form_state);

    $markup_keys = [
      'hidden_decisions_text',
      'non_public_attachments_text',
      'documents_description',
      'meetings_description',
      'recording_description',
      'decisions_description',
      'meeting_calendar_description',
      'decision_search_description',
      'policymakers_search_description',
      'banner_text',
    ];

    $config = $this->config(self::SETTINGS);
    foreach ($markup_keys as $key) {
      $config->set("$key.value", $form_state->getValue($key)['value']);
      $config->set("$key.format", $form_state->getValue($key)['format']);
    }

    $config->save();
  }

}
