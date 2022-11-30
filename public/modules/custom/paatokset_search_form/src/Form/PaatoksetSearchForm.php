<?php

namespace Drupal\paatokset_search_form\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Front page search form.
 */
class PaatoksetSearchForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'paatokset_search_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['container'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'paatokset__search-form',
        'class' => ['paatokset__search-form'],
      ],
    ];

    $form['container']['search-wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['search-wrapper'],
      ],
    ];

    $form['container']['search-wrapper']['hds-text-input'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['hds-text-input'],
      ],
    ];

    $form['container']['search-wrapper']['hds-text-input']['hds-text-input__input-wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['hds-text-input__input-wrapper'],
      ],
    ];

    $form['container']['search-wrapper']['hds-text-input']['hds-text-input__input-wrapper']['hds-text-input__input'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'class' => ['hds-text-input__input'],
      ],
      '#title' => t('Search from content'),
      '#placeholder' => t('Search with a Finnish keyword, eg. Viikki'),
    ];

    $form['container']['search-wrapper']['hds-text-input']['hds-text-input__input-wrapper']['submit'] = [
      '#type' => 'submit',
      '#value' => t('Search'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $currentLanguage = \Drupal::languageManager()->getCurrentLanguage()->getId();
    if ($currentLanguage === 'fi' || $currentLanguage === 'sv') {
      $route = 'paatokset_search.decisions.' . $currentLanguage;
    }
    else {
      $route = 'paatokset_search.decisions.fi';
    }
    $form_state->setRedirect($route, ['s' => json_encode($form_state->getValue(['hds-text-input__input']))]);
  }

}
