<?php

namespace Drupal\paatokset_search\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form for Paatokset Search settings.
 */
class PaatoksetSearchForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'paatokset_search.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'paatokset_search_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('paatokset_search.settings');

    $form['city_hall_id'] = [
      '#type' => 'textfield',
      '#default_value' => $config->get('city_hall_id'),
      '#title' => t('City Hall ID'),
      '#description' => 'Id for the city hall policymaker',
    ];

    $form['trustee_organization_type_id'] = [
      '#type' => 'textfield',
      '#default_value' => $config->get('trustee_organization_type_id'),
      '#title' => t('Trustee organization type id'),
      '#description' => 'Id for the trustee type for policymakers',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('paatokset_search.settings')
      ->set('city_hall_id', $form_state->getValue('city_hall_id'))
      ->set('trustee_organization_type_id', $form_state->getValue('trustee_organization_type_id'))
      ->save();
  }

}
