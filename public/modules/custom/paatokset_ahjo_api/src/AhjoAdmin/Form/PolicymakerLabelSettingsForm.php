<?php

namespace Drupal\paatokset_ahjo_api\AhjoAdmin\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings form for Policymaker label settings.
 */
final class PolicymakerLabelSettingsForm extends ConfigFormBase {

  /**
   * The database.
   */
  protected Connection $database;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    $instance = parent::create($container);
    $instance->database = $container->get(Connection::class);
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'paatokset_policymaker_label_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames(): array {
    return [
      'paatokset_ahjo_api.policymaker_labels',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
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
          '#title' => $lang,
          '#config_target' => "paatokset_ahjo_api.policymaker_labels:{$key}_{$code}",
        ];
      }
    }

    return parent::buildForm($form, $form_state);
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
