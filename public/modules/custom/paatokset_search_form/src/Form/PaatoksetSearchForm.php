<?php

namespace Drupal\paatokset_search_form\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

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
      '#placeholder' => t('Write a search phrase, eg. park'),
    ];

    $form['container']['search-wrapper']['hds-text-input']['hds-text-input__input-wrapper']['submit'] = [
      '#type' => 'submit',
      '#value' => t('Search'),
      '#button_type' => 'primary',
    ];

    $form['container']['advanced-search-link-container'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['advanced-search-link-container'],
      ],
    ];

    $form['container']['advanced-search-link-container']['advanced-search-link'] = [
      '#type' => 'link',
      '#title' => t('Show advanced search'),
      '#url' => Url::fromRoute('entity.node.canonical', ['node' => 1]),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // @todo Implement validation
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // @todo Implement submit
    return TRUE;
  }

  /**
   * Determine and return advanced search form link.
   *
   * @return array
   *   String, url
   */
  private function getAdvancedSearchLink() {
    // @todo implement
    return '/advanced-search';
  }

}
