<?php

namespace Drupal\paatokset_search_form\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Front page search form.
 */
class PolicymakerSearchForm extends FormBase {

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
    // Library attachments.
    $form['#attached']['library'][] = 'helfi_paatokset/litepicker';
    $form['#attached']['library'][] = 'select2/select2.min';
    $form['#attached']['library'][] = 'paatokset_search_form/SubmitForm';

    $form['container'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'paatokset__search-form',
        'class' => ['paatokset__search-form'],
      ],
    ];

    $form['container']['title'] = [
      '#type' => 'markup',
      '#markup' => '<h2>' . t('Search policymakers') . '</h2>',
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

    $search_phrase = \Drupal::request()->query->get('title');
    $form['container']['search-wrapper']['hds-text-input']['hds-text-input__input-wrapper']['hds-text-input__input'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'class' => ['hds-text-input__input'],
      ],
      '#placeholder' => t('Write a search phrase, eg. park'),
      '#default_value' => $search_phrase,
    ];

    $form['container']['search-wrapper']['hds-text-input']['hds-text-input__input-wrapper']['submit'] = [
      '#type' => 'submit',
      '#value' => t('Search'),
      '#button_type' => 'primary',
    ];

    $form['container']['org-type__container'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['org-type__container']],
    ];

    $org_type = \Drupal::request()->query->get('org_type');
    $form['container']['org-type__container']['org-type'] = [
      '#default_value' => $org_type,
      '#type' => 'select',
      '#title' => t('Topic'),
      '#options' => $this->getFilters(),
      '#multiple' => TRUE,
      '#attributes' => [
        'class' => [
          'paatokset-checked-dropdown',
          'checked-dropdown',
        ],
        'data-placeholder' => t('Choose topic'),
      ],
      '#ajax' => [
        'callback' => '::selectDefault',
        'wrapper' => 'edit-tags',
        'event' => 'change',
      ],
    ];

    $request = $this->getRequest();
    if ($request->getMethod() === 'GET') {
      $form_state->setValue('org-type', $org_type);
    }

    $tags = $this->getCurrentTags($form_state);
    $form['container']['advanced-search__tags-container'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['advanced-search__tags-container'],
      ],
      '#prefix' => '<div id="edit-tags">',
      '#suffix' => '</div>',
      '#tags' => $this->getCurrentTags($form_state),
    ];

    return $form;
  }

  /**
   * Return current available filters.
   */
  private function getFilters() {
    $connection = \Drupal::database();
    $query = $connection->select('node__field_organization_type', 'nfot');
    $query->fields('nfot', ['field_organization_type_value']);
    $results = $query->distinct()->execute()->fetchCol();

    $transformed_results = [];
    foreach ($results as $result) {
      $transformed_results[$result] = str_replace('_', ' ', ucfirst($result));
    }

    return $transformed_results;
  }

  /**
   * Rebuild form and return tags container.
   */
  public function selectDefault(array &$form, FormstateInterface $form_state) {
    $form_state->setRebuild();

    return $form['container']['advanced-search__tags-container'];
  }

  /**
   * Return currently selected tags.
   */
  public function getCurrentTags(FormstateInterface $form_state) {
    $tags = [];

    $org_types = $form_state->getValue('org-type');
    if ($org_types) {
      $tags['org_types'] = $org_types;
    }

    return $tags;
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
    $query = [];
    $values = $form_state->cleanValues()->getValues();
    if (!empty($values['hds-text-input__input'])) {
      $query['title'] = $values['hds-text-input__input'];
    }
    if (!empty($values['org-type'])) {
      $query['org_type'] = $values['org-type'];
    }

    $url = Url::fromRoute('<current>', [], ['query' => $query]);
    $form_state->setRedirectUrl($url);
  }

}
