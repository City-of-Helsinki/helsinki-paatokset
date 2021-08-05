<?php

namespace Drupal\paatokset_search_form\Form;

use Drupal\Component\DateTime\DateTimePlus;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\paatokset_search_form\Ajax\SubmitSearch;

/**
 * Search form with additional search fields.
 */
class AdvancedSearchForm extends FormBase {
  const ANYTIME = 'any_time';
  const THIS_WEEK = 'this_week';
  const THIS_MONTH = 'this_month';
  const THIS_YEAR = 'this_year';
  const TRUSTEES = 'trustees';
  const VIEW = '.views--decisions-search';

  /**
   * Available options for policymakers.
   *
   * @var policymakers
   */
  private $policymakers;

  /**
   * Class constructor.
   */
  public function __construct() {
    $this->connection = $connection = \Drupal::database();
    $this->language = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $this->loadPolicymakers();
  }

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
    $form['#attached']['library'][] = 'paatokset_search_form/SubmitForm';
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
        'class' => [
          'hds-text-input__input-wrapper', 'search-bar__input-wrapper',
        ],
      ],
    ];

    $search_phrase = \Drupal::request()->query->get('s');
    $form['container']['search-wrapper']['hds-text-input']['hds-text-input__input-wrapper']['search_phrase'] = [
      '#type' => 'textfield',
      '#title' => t('Search decisions'),
      '#default_value' => $search_phrase,
      '#attributes' => [
        'class' => [
          'hds-text-input__input',
          'search-bar',
        ],
      ],
      '#placeholder' => t('Write a search phrase, eg. park'),
    ];

    $form['container']['search-wrapper']['hds-text-input']['hds-text-input__input-wrapper']['submit'] = [
      '#type' => 'submit',
      '#value' => t('Search'),
      '#button_type' => 'primary',
      '#attributes' => [
        'class' => ['submit__desktop'],
      ],
      '#ajax' => [
        'callback' => '::submitForm',
      ],
    ];

    $form['container']['advanced-fields-container'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['advanced-fields-container'],
      ],
    ];

    $form['container']['advanced-fields-container']['advanced-search__use-calendar'] = [
      '#type' => 'checkbox',
      '#hidden' => 'true',
      '#ajax' => [
        'callback' => '::selectDefault',
        'wrapper' => 'advanced-fields-container',
        'event' => 'change',
      ],
    ];

    $form['container']['advanced-fields-container']['date'] = [
      '#type' => 'select',
      '#title' => t('Date'),
      '#options' => $this->customTimeFilters(),
      '#attributes' => [
        'class' => [
          'advanced-search__date-field',
          'checked-dropdown',
        ],
        'data-placeholder' => t('Pick a date'),
      ],
      '#states' => [
        'visible' => [
          'input[name="advanced-search__use-calendar"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['container']['advanced-fields-container']['advanced-search__date-calendar'] = [
      '#type' => 'textfield',
      '#title' => t('Date'),
      '#attributes' => [
        'data-placeholder' => t('Pick a date'),
      ],
      '#states' => [
        'visible' => [
          'input[name="advanced-search__use-calendar"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['container']['advanced-fields-container']['date-range__container'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['date-range__container'],
      ],
    ];

    $form['container']['advanced-fields-container']['date-range__container']['date_from'] = [
      '#type' => 'date',
      '#hidden' => TRUE,
      '#attributes' => [
        'class' => ['date-from'],
        'type' => 'date',
      ],
    ];

    $form['container']['advanced-fields-container']['date-range__container']['date_to'] = [
      '#type' => 'date',
      '#hidden' => TRUE,
      '#attributes' => [
        'class' => ['date-to'],
        'type' => 'date',
      ],
    ];

    $form['container']['advanced-fields-container']['advanced-search__policymaker-field-container'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['advanced-search__policymaker-field-container'],
      ],
    ];

    $queryPolicymakers = \Drupal::request()->query->get('policymakers');
    $form['container']['advanced-fields-container']['advanced-search__policymaker-field-container']['policymakers'] = [
      '#type' => 'select',
      '#title' => t('Policymaker'),
      '#default_value' => $queryPolicymakers,
      '#options' => $this->policymakers,
      '#multiple' => TRUE,
      '#ajax' => [
        'callback' => '::selectDefault',
        'wrapper' => 'edit-tags',
        'event' => 'change',
      ],
      '#attributes' => [
        'class' => [
          'paatokset-checked-dropdown',
          'checked-dropdown',
        ],
        'data-placeholder' => t('Choose policymaker'),
      ],
    ];

    $form['container']['advanced-fields-container']['advanced-search__topic-field-container'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['advanced-search__topic-field-container'],
      ],
    ];

    $topics = $this->getTopics();
    $queryTopics = \Drupal::request()->query->get('topics');
    $form['container']['advanced-fields-container']['advanced-search__topic-field-container']['topics'] = [
      '#type' => 'select',
      '#title' => t('Topic'),
      '#default_value' => $queryTopics,
      '#options' => $topics,
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
        'event' => 'select2:change',
      ],
    ];

    $form['container']['submit'] = [
      '#type' => 'submit',
      '#value' => t('Search'),
      '#button_type' => 'primary',
      '#attributes' => [
        'class' => ['submit__mobile'],
      ],
      '#ajax' => [
        'callback' => '::submitForm',
      ],
    ];

    $request = $this->getRequest();
    if ($request->getMethod() === 'GET') {
      $form_state->setValue('search_phrase', $search_phrase);
      $form_state->setValue('policymakers', $queryPolicymakers);
      $form_state->setValue('topics', $queryTopics);
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
   * Reuild form and return tags container.
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

    $policymakers = $form_state->getValue('policymakers');
    if ($policymakers) {
      if (!$this->policymakers) {
        $this->loadPolicymakers();
      }

      foreach ($policymakers as $policymaker) {
        if ($policymaker === self::TRUSTEES) {
          $tags['policymaker'][$policymaker] = $policymaker;
        }
        $tags['policymaker'][$policymaker] = $this->policymakers[$policymaker];
      }
    }

    $topics = $form_state->getValue('topics');
    if ($topics) {
      foreach ($topics as $topic) {
        $tags['topic'][$topic] = $topic;
      }
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
    $response = new AjaxResponse();

    $data = $this->constructResponse($form_state);
    $submitCommand = new SubmitSearch(self::VIEW, $data['url'], $data['view']);
    $response->addCommand($submitCommand);
    return $response;
  }

  /**
   * Construct new URL.
   */
  private function constructResponse(FormStateInterface $form_state) {
    $values = $form_state->cleanValues()->getValues();
    $params = [];

    foreach ($values as $key => $value) {
      if (!empty($value) || $value === 0) {
        if ($key === 'search_phrase') {
          $params['s'] = $value;
        }
        if ($key === 'advanced-search__use-calendar') {
          if ($value === 0 && !empty($values['date'] && $values['date'] !== self::ANYTIME)) {
            $date_from = NULL;
            $date_to = strtotime('now');
            switch ($values['date']) {
              case self::THIS_WEEK:
                $date_from = strtotime('-1 week');
                break;

              case self::THIS_MONTH:
                $date_from = strtotime('-1 month');
              case self::THIS_YEAR:
                $date_from = strtotime('-1 year');
                break;
            }
            $params['date_from'] = DateTimePlus::createFromTimeStamp($date_from)->format('Y-m-d');
            $params['date_to'] = DateTimePlus::createFromTimeStamp($date_to)->format('Y-m-d');
          }
          else {
            if (!empty($values['date_from'])) {
              $params['date_from'] = $values['date_from'];
            }
            if (!empty($values['date_to'])) {
              $params['date_to'] = $values['date_to'];
            }
          }
        }
        if ($key === 'topics' || $key === 'policymakers') {
          $params[$key] = $value;
        }
      }
    }

    // Reset query params.
    foreach (\Drupal::request()->query->all() as $key => $values) {
      if (in_array($key, [
        's',
        'date_form',
        'date_to',
        'policymakers',
        'topics',
      ])) {
        \Drupal::request()->query->remove($key);
      }
    }

    foreach ($params as $key => $param) {
      \Drupal::request()->query->set($key, $param);
    }

    $view = [
      '#type' => 'view',
      '#name' => 'decisions_search',
      '#display_id' => 'block_1',
      '#arguments' => isset($params['s']) ? ['s' => $params['s']] : [],
      '#embed' => TRUE,
    ];

    $result = \Drupal::service('renderer')->render($view);

    return [
      'url' => Url::fromRoute('<current>', [], ['query' => $params])->toString(),
      'view' => $result,
    ];
  }

  /**
   * Set policymaker instance variable.
   */
  private function loadPolicymakers() {
    $query = $this->connection->select('node_field_data', 'nfd');
    $query->join('node__field_resource_uri', 'nfru', 'nfd.nid = nfru.entity_id');
    $query->join('node__field_organization_type', 'nfot', 'nfd.nid = nfot.entity_id');
    $query->fields('nfru', ['field_resource_uri_value']);
    $query->fields('nfd', ['title']);
    $query->condition('nfd.langcode', $this->language);
    $query->condition('nfru.langcode', $this->language);
    $query->condition('nfot.langcode', $this->language);
    $query->condition('nfd.type', 'policymaker');
    $query->condition('nfot.field_organization_type_value', 'trustee', '!=');
    $query->orderBy('nfd.title', 'ASC');
    $results = $query->distinct()->execute()->fetchAllKeyed();

    $this->policymakers = [self::TRUSTEES => t('Trustees')] + $results;
  }

  /**
   * Return topics as options.
   *
   * @return array
   *   Array of topics
   */
  private function getTopics() {
    $query = $this->connection->select('paatokset_issue_field_data', 'pifd');
    $query->fields('pifd', ['top_category_name']);
    $query->condition('langcode', $this->language);
    $results = $query->distinct()->execute()->fetchCol();

    $transformed_results = [];
    foreach ($results as $result) {
      $transformed_results[$result] = $result;
    }

    return $transformed_results;
  }

  /**
   * Predetermined time frames as options.
   */
  private function customTimeFilters() {
    return [
      self::ANYTIME => t('Any time'),
      self::THIS_WEEK => t('This week'),
      self::THIS_MONTH => t('This month'),
      self::THIS_YEAR => t('This year'),
    ];
  }

}
