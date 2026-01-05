<?php

declare(strict_types=1);

namespace Drupal\paatokset_search\Form;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\paatokset_search\SearchManager;

/**
 * Decision search form.
 */
class DecisionSearchForm extends FormBase {

  use AutowireTrait;

  public function __construct(
    private readonly LanguageManagerInterface $languageManager,
    private readonly SearchManager $searchManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'paatokset_decision_search_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#attributes']['class'][] = 'paatokset-decision-search-form';

    $form['q'] = [
      '#type' => 'paatokset_textfield_autocomplete',
      '#maxlength' => 1028,
      '#autocomplete_route_name' => 'paatokset_search.autocomplete',
      '#operator_guide_url' => $this->searchManager->getOperatorGuideUrl(),
      '#title' => $this->t('Search decisions', [], ['context' => 'Decisions search']),
      '#placeholder' => $this->t(
        'Search with a Finnish keyword, eg. Viikki',
        [],
        ['context' => 'Decisions search']
      ),
      '#required' => TRUE,
      '#default_value' => $this->getRequest()?->query->get('q', ''),
      '#attributes' => [
        'class' => [
          'hdbt-search__filter',
        ],
      ],
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
      '#attributes' => [
        'class' => [
          'hds-button',
          'hds-button--primary',
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $search = $form_state->getValue('q');

    $langcode = $this->languageManager
      ->getCurrentLanguage()
      ->getId();

    $route_name = match ($langcode) {
      'sv' => 'paatokset_search.decisions.sv',
      'en' => 'paatokset_search.decisions.en',
      default => 'paatokset_search.decisions.fi',
    };

    $form_state->setRedirect(
      $route_name,
      [],
      [
        'query' => [
          's' => $search,
          'page' => 1,
          'sort' => 'relevance',
        ],
      ],
    );
  }

}
