<?php

namespace Drupal\paatokset_ahjo_api\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings form for Päätökset default settings.
 *
 * @package Drupal\paatokset_ahjo_api\Form
 */
class DefaultTextSettingsForm extends ConfigFormBase {

  /**
   * Config settings.
   */
  public const SETTINGS = 'paatokset_ahjo_api.default_texts';

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    $instance = parent::create($container);
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'paatokset_default_text_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames() {
    return [
      self::SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(self::SETTINGS);

    $form['links'] = [
      '#type' => 'details',
      '#title' => $this->t('Links and URLs'),
      '#open' => TRUE,
    ];

    $form['links']['committees_boards_url'] = [
      '#type' => 'textfield',
      '#default_value' => $config->get('committees_boards_url'),
      '#title' => $this->t('Committees and boards URL'),
      '#description' => $this->t('Used on the decision tree page.'),
    ];

    $form['links']['office_holders_url'] = [
      '#type' => 'textfield',
      '#default_value' => $config->get('office_holders_url'),
      '#title' => $this->t('Office holders URL'),
      '#description' => $this->t('Used on the decision tree page.'),
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
      '#default_value' => $config->get('calendar_notice_text'),
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
      '#default_value' => $config->get('banner_heading'),
      '#title' => $this->t('Banner heading'),
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
      '#default_value' => $config->get('banner_label'),
      '#title' => $this->t('CTA button label'),
    ];

    $form['banner']['banner_url'] = [
      '#type' => 'textfield',
      '#default_value' => $config->get('banner_url'),
      '#title' => $this->t('CTA button link'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $default_texts = [
      'calendar_notice_text' => $form_state->getValue('calendar_notice_text'),
      'committees_boards_url' => $form_state->getValue('committees_boards_url'),
      'office_holders_url' => $form_state->getValue('office_holders_url'),
      'hidden_decisions_text.value' => $form_state->getValue('hidden_decisions_text')['value'],
      'hidden_decisions_text.format' => $form_state->getValue('hidden_decisions_text')['format'],
      'non_public_attachments_text.value' => $form_state->getValue('non_public_attachments_text')['value'],
      'non_public_attachments_text.format' => $form_state->getValue('non_public_attachments_text')['format'],
      'documents_description.value' => $form_state->getValue('documents_description')['value'],
      'documents_description.format' => $form_state->getValue('documents_description')['format'],
      'meetings_description.value' => $form_state->getValue('meetings_description')['value'],
      'meetings_description.format' => $form_state->getValue('meetings_description')['format'],
      'recording_description.value' => $form_state->getValue('recording_description')['value'],
      'recording_description.format' => $form_state->getValue('recording_description')['format'],
      'decisions_description.value' => $form_state->getValue('decisions_description')['value'],
      'decisions_description.format' => $form_state->getValue('decisions_description')['format'],
      'meeting_calendar_description.value' => $form_state->getValue('meeting_calendar_description')['value'],
      'meeting_calendar_description.format' => $form_state->getValue('meeting_calendar_description')['format'],
      'decision_search_description.value' => $form_state->getValue('decision_search_description')['value'],
      'decision_search_description.format' => $form_state->getValue('decision_search_description')['format'],
      'policymakers_search_description.value' => $form_state->getValue('policymakers_search_description')['value'],
      'policymakers_search_description.format' => $form_state->getValue('policymakers_search_description')['format'],
      'banner_heading' => $form_state->getValue('banner_heading'),
      'banner_text.value' => $form_state->getValue('banner_text')['value'],
      'banner_text.format' => $form_state->getValue('banner_text')['format'],
      'banner_label' => $form_state->getValue('banner_label'),
      'banner_url' => $form_state->getValue('banner_url'),
    ];

    $config = $this->config(self::SETTINGS);

    foreach ($default_texts as $key => $value) {
      $config->set($key, $value);
    }

    $config->save();
  }

}
