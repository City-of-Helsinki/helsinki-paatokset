<?php

namespace Drupal\paatokset_ahjo_api\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Html;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings form for Policymaker label settings.
 *
 * @package Drupal\paatokset_ahjo_api\Form
 */
class PolicymakerLabelSettingsForm extends ConfigFormBase {

  /**
   * The database.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    $instance = parent::create($container);
    $instance->database = $container->get('database');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'paatokset_policymaker_label_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames() {
    return [
      'paatokset_ahjo_api.policymaker_labels',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('paatokset_ahjo_api.policymaker_labels');

    $labels = $this->getPolicymakerLabels();
    $languages = [
      'fi' => 'Finnish',
      'sv' => 'Swedish',
      'en' => 'English',
    ];

    foreach ($labels as $key => $value) {
      $form[$key . '_details'] = [
        '#type' => 'details',
        '#title' => $value,
        '#open' => FALSE,
      ];

      foreach ($languages as $code => $lang) {
        $form[$key . '_details'][$key . '_' . $code] = [
          '#type' => 'textfield',
          '#default_value' => $config->get($key . '_' . $code),
          '#title' => $lang,
        ];
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $labels = $this->getPolicymakerLabels();
    $config = $this->config('paatokset_ahjo_api.policymaker_labels');
    $languages = ['fi', 'sv', 'en'];

    foreach ($labels as $key => $value) {
      foreach ($languages as $langcode) {
        $config->set($key . '_' . $langcode, $form_state->getValue($key . '_' . $langcode));
      }
    }

    $config->save();
  }

  /**
   * Get all distinct policymaker label values from database.
   *
   * @return array
   *   List of labels in key value format.
   */
  private function getPolicymakerLabels(): array {
    $query = $this->database->select('node__field_organization_type', 'n')
      ->fields('n', ['field_organization_type_value'])
      ->distinct();

    $labels = [];
    $results = $query->execute()->fetchAll();
    foreach ($results as $result) {
      if (empty(trim($result->field_organization_type_value))) {
        continue;
      }
      $key = $this->getIdFromLabel($result->field_organization_type_value);
      $labels[$key] = $result->field_organization_type_value;
    }

    // Add "Kaupunginhallituksen jaosto" manually.
    $labels['kaupunginhallituksen-jaosto'] = 'Kaupunginhallituksen jaosto';
    return $labels;
  }

  /**
   * Translate label to ID.
   *
   * @param string $label
   *   Label to get ID for.
   *
   * @return string
   *   Config ID.
   */
  private function getIdFromLabel(string $label): string {
    return Html::cleanCssIdentifier(strtolower($label));
  }

}
