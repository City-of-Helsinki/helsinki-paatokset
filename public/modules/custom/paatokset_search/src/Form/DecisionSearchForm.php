<?php

declare(strict_types=1);

namespace Drupal\paatokset_search\Form;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Decision search form.
 */
class DecisionSearchForm extends FormBase implements ContainerInjectionInterface {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * Constructs the form.
   */
  public function __construct(LanguageManagerInterface $language_manager) {
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('language_manager'),
    );
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
      '#autocomplete_route_name' => 'helfi_api_base.location_autocomplete',
      '#title' => $this->t('Search decisions'),
      '#placeholder' => $this->t(
        'Search with a Finnish keyword, eg. Viikki',
        [],
        ['context' => 'Decisions search']
      ),
      '#required' => TRUE,
      '#default_value' => $this->getRequest()?->query->get('q', ''),
      '#attributes' => [
        'class' => [
          'hds-text-input',
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

    $language = $this->languageManager
      ->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)
      ->getId();

    $route_map = [
      'fi' => 'paatokset_search.decisions.fi',
      'sv' => 'paatokset_search.decisions.sv',
      'en' => 'paatokset_search.decisions.en',
    ];

    $route_name = $route_map[$language] ?? 'paatokset_search.decisions.fi';

    $form_state->setRedirect(
      $route_name,
      [],
      [
        'query' => [
          's' => $search,
          'page' => 1,
          'sort' => 'relevance',
        ],
      ]
    );
  }

}
