<?php

declare(strict_types = 1);

namespace Drupal\paatokset_council_info\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\FileInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use League\Csv\Reader;

/**
 * Councilmember info import form.
 */
class InfoImportForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'council_info_import';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attributes'] = ['enctype' => 'multipart/form-data'];

    $form['file'] = [
      '#type' => 'details',
      '#title' => $this->t('Info file'),
      '#open' => TRUE,
    ];
    $form['file']['info_file'] = [
      '#type' => 'managed_file',
      '#name' => 'info_file',
      '#title' => $this->t('File upload'),
      '#description' => $this->t('Councilmember data as a CSV file. Should use ; as delimiter.'),
      '#upload_validators' => [
        'file_validate_extensions' => ['csv'],
      ],
      '#upload_location' => 'public://council_info/',
    ];

    $form['file']['existing_info_file'] = [
      '#type' => 'entity_autocomplete',
      '#name' => 'existing_info_file',
      '#target_type' => 'file',
      '#title' => $this->t('Existing file'),
      '#description' => $this->t('Use a previously uploaded file.'),
    ];

    $form['file']['file_storage'] = [
      '#type' => 'radios',
      '#title' => $this->t('File storage options'),
      '#description' => $this->t('Set or change file storage options.'),
      '#options' => [
        'permanent' => $this->t('Permanent file'),
        'temporary' => $this->t('Temporary file'),
        'do_nothing' => $this->t('No change (store uploaded as temporary)'),
      ],
      '#default_value' => 'do_nothing',
    ];

    $form['submit_button'] = [
      '#type' => 'submit',
      '#value' => $this->t('Start import'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (empty($form_state->getValue('info_file')) && $form_state->getValue('existing_info_file') === NULL) {
      $form_state->setErrorByName('file', $this->t('File missing.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $file_id = NULL;
    if (!empty($form_state->getValue('info_file'))) {
      $file_id = reset($form_state->getValue('info_file'));
    }
    elseif (!empty($form_state->getValue('existing_info_file'))) {
      $file_id = $form_state->getValue('existing_info_file');
    }
    else {
      $this->messenger->addError('File missing.');
      return;
    }

    $entity_manager = \Drupal::service('entity_type.manager');
    $file_storage = $entity_manager->getStorage('file');
    $messenger = \Drupal::service('messenger');
    $file = $file_storage->load($file_id);

    if (!$file instanceof FileInterface) {
      $messenger->addError('File could not be loaded.');
      return;
    }

    if ($file->get('filemime')->value !== 'text/csv') {
      $messenger->addError('File must be a CSV file.');
      return;
    }

    $file_setting = $form_state->getValue('file_storage');
    if ($file_setting === 'permanent') {
      $file->setPermanent();
      $file->save();
      $messenger->addMessage('File is now stored permanently.');
    }
    elseif ($file_setting === 'temporary') {
      $file->setTemporary();
      $file->save();
      $messenger->addMessage('File storage set to temporary.');
    }

    try {
      $csv_reader = $this->getCsvReader($file);
    }
    catch (\Exception $e) {
      $messenger->addMessage($e->getMessage(), 'error');
      return;
    }

    $operations = [];
    $count = 0;
    foreach ($csv_reader->getRecords() as $record) {
      $count++;
      $record['count'] = $count;
      $operations[] = [
        '\Drupal\paatokset_council_info\Form\InfoImportForm::doProcess', [
          $record,
        ],
      ];
    }

    if (empty($operations)) {
      $messenger->addWarning('Nothing to import');
      return;
    }

    $batch = [
      'title' => $this->t('Importing council member information.'),
      'operations' => $operations,
      'init_message'     => $this->t('Starting batch'),
      'progress_message' => $this->t('Processed @current out of @total.'),
      'error_message'    => $this->t('An error occurred during processing'),
      'finished' => '\Drupal\paatokset_council_info\Form\InfoImportForm::finishedCallback',
    ];

    batch_set($batch);
  }

  /**
   * Get CSV reader for file.
   *
   * @param \Drupal\file\FileInterface $file
   *   File to load CSV data from.
   *
   * @return \League\Csv\Reader
   *   CSV reader.
   */
  protected function getCsvReader(FileInterface $file): Reader {
    $contents = file_get_contents($file->getFileUri());
    $reader = Reader::createFromString($contents);
    $reader->setDelimiter(';');
    $reader->setHeaderOffset(0);
    return $reader;
  }

  /**
   * Static callback for processing batch items.
   *
   * @param mixed $data
   *   Data for operation.
   * @param mixed $context
   *   Context for batch operation.
   */
  public static function doProcess($data, &$context) {
    $logger = \Drupal::logger('paatokset_council_info');
    $context['message'] = 'Importing item number ' . $data['count'];
    if (!isset($context['results']['items'])) {
      $context['results']['items'] = [];
    }
    if (!isset($context['results']['failed'])) {
      $context['results']['failed'] = [];
    }

    $query = \Drupal::entityQuery('node')
      ->accessCheck(TRUE)
      ->condition('status', 1)
      ->condition('type', 'trustee')
      ->condition('field_first_name', trim($data['Etunimet']))
      ->condition('field_last_name', trim($data['Sukunimi']))
      ->range('0', '1');

    $ids = $query->execute();

    if (!empty($ids)) {
      $node = Node::load(reset($ids));
      self::updateNode($node, $data);
      $logger->info('Updated note: ' . $node->title->value . ' (' . $node->id() . ').');
      $context['results']['items'][] = $data;
    }
    else {
      $logger->warning('Could not find node for ' . $data['Sukunimi'] . ', ' . $data['Etunimet']);
      $context['results']['failed'][] = $data;
    }
  }

  /**
   * Update node with data from CSV.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Node to update.
   * @param array $data
   *   Data from CSV.
   */
  public static function updateNode(NodeInterface $node, array $data): void {
    $updated = FALSE;
    foreach ($data as $key => $value) {
      $field = self::getFieldKey($key);

      // Do nothing if field can't be found or if field is empty.
      if (!$field || empty($value)) {
        continue;
      }

      // Trim whitespace.
      if ($value instanceof string) {
        $value = trim($value);
      }

      // Validation for homepage links.
      if ($field === 'field_trustee_homepage' && !UrlHelper::isValid($value, TRUE)) {
        continue;
      }

      // Only update node if values are present.
      $updated = TRUE;

      // If value is '-' erase previous value.
      if ($value === '-') {
        $node->set($field, NULL);
      }
      else {
        $node->set($field, $value);
      }
    }

    if ($updated) {
      $node->save();
    }
  }

  /**
   * Get field ID by CSV header.
   *
   * @param string $header
   *   CSV header to check.
   *
   * @return string|null
   *   Mapped field ID or NULL.
   */
  public static function getFieldKey(string $header): ?string {
    switch ($header) {
      case "Kotikaupunginosa":
        $key = 'field_trustee_home_district';
        break;

      case "Puhelinnumero verkossa":
        $key = 'field_trustee_phone';
        break;

      case "Sähköposti verkossa":
        $key = 'field_trustee_email';
        break;

      case "Kotisivu":
        $key = 'field_trustee_homepage';
        break;

      case "Ammatti":
        $key = 'field_trustee_profession';
        break;

      default:
        $key = NULL;
        break;
    }
    return $key;
  }

  /**
   * Static callback function for finishing batch.
   *
   * @param mixed $success
   *   If batch succeeded or not.
   * @param array $results
   *   Aggregated results.
   * @param array $operations
   *   Operations with errors.
   */
  public static function finishedCallback($success, array $results, array $operations) {
    $message = 'Processed ' . count($results['items']) . ' items with ' . count($results['failed']) . ' items failed.';
    $logger = \Drupal::logger('paatokset_council_info');
    $logger->info($message);
    $messenger = \Drupal::messenger();
    $messenger->addMessage($message);
  }

}
