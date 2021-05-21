<?php

namespace Drupal\paatokset_search_form\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Search form with additional search fields.
 */
class AdvancedSearchForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'paatokset_advanced_search_form';
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

    $form['container']['advanced-fields-container'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['advanced-fields-container'],
      ],
    ];

    $form['container']['advanced-fields-container']['advanced-search__date-field'] = [
      '#type' => 'select',
      '#title' => t('Date'),
      '#options' => $this->getTopics(),
      '#attributes' => [
        'class' => ['advanced-search__date-field'],
      ],
    ];

    $form['container']['advanced-fields-container']['advanced-search__decider-field'] = [
      '#type' => 'select',
      '#title' => t('Policymaker'),
      '#options' => $this->getTopics(),
      '#attributes' => [
        'class' => ['advanced-search__date-field'],
      ],
    ];

    $form['container']['advanced-fields-container']['advanced-search__topic-field'] = [
      '#type' => 'select',
      '#title' => t('Topic'),
      '#options' => $this->getTopics(),
      '#attributes' => [
        'class' => ['advanced-search__date-field'],
      ],
    ];

    $form['container']['tags-container'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['advanced-search__tags-container'],
      ],
      '#tags' => ['Tag 1'],
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
   * Return topics as options.
   *
   * @return array
   *   Array of topics
   */
  private function getTopics() {
    // @todo implement
    return [
      'Dropdown item value' => 'Dropdown item title',
    ];
  }

}
