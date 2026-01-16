<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\Form;

use Drupal\Core\Form\FormState;
use Drupal\paatokset_ahjo_api\Form\DefaultTextSettingsForm;
use Drupal\Tests\paatokset_ahjo_api\Kernel\AhjoEntityKernelTestBase;

/**
 * Tests the DefaultTextSettingsForm form.
 */
class DefaultTextSettingsFormTest extends AhjoEntityKernelTestBase {

  /**
   * Tests form submission.
   */
  public function testFormSubmission(): void {
    $form_object = DefaultTextSettingsForm::create($this->container);
    $form_state = new FormState();
    $form_array = $form_object->buildForm([], $form_state);

    // Check that some expected elements exist.
    $this->assertArrayHasKey('links', $form_array);
    $this->assertArrayHasKey('alerts', $form_array);
    $this->assertArrayHasKey('defaults', $form_array);
    $this->assertArrayHasKey('banner', $form_array);

    // Fields with 'value' and 'format' structure.
    $rich_text_fields = [
      'hidden_decisions_text',
      'non_public_attachments_text',
      'documents_description',
      'meetings_description',
      'recording_description',
      'decisions_description',
      'meeting_calendar_description',
      'decision_search_description',
      'policymakers_search_description',
      'banner_text',
    ];

    // Fields with simple text values.
    $simple_text_fields = [
      'calendar_notice_text',
      'banner_heading',
      'banner_label',
    ];

    // Fields with URL values.
    $url_fields = [
      'committees_boards_url',
      'office_holders_url',
      'banner_url',
    ];

    $form_values = [];

    // Create rich text fields.
    foreach ($rich_text_fields as $field) {
      $form_values[$field] = [
        'value' => "Test content: $field",
        'format' => 'full_html',
      ];
    }

    // Create simple text fields.
    foreach ($simple_text_fields as $field) {
      $form_values[$field] = "Test content: $field";
    }

    // Create URL fields.
    foreach ($url_fields as $field) {
      $form_values[$field] = 'https://www.test.hel.ninja';
    }

    $form_state->setValues($form_values);

    // Perform submit (assuming submit handler uses config saving).
    $form_object->submitForm($form_array, $form_state);

    // Get the saved values from configurations.
    $config_factory = $this->container->get('config.factory');
    $config = $config_factory->getEditable(DefaultTextSettingsForm::SETTINGS);

    // Assert that the config values are correctly saved.
    $assertions = [
      'calendar_notice_text' => ['calendar_notice_text'],
      'committees_boards_url' => ['committees_boards_url'],
      'office_holders_url' => ['office_holders_url'],
      'hidden_decisions_text.value' => ['hidden_decisions_text', 'value'],
      'hidden_decisions_text.format' => ['hidden_decisions_text', 'format'],
      'non_public_attachments_text.value' => ['non_public_attachments_text', 'value'],
      'non_public_attachments_text.format' => ['non_public_attachments_text', 'format'],
      'documents_description.value' => ['documents_description', 'value'],
      'documents_description.format' => ['documents_description', 'format'],
      'meetings_description.value' => ['meetings_description', 'value'],
      'meetings_description.format' => ['meetings_description', 'format'],
      'recording_description.value' => ['recording_description', 'value'],
      'recording_description.format' => ['recording_description', 'format'],
      'decisions_description.value' => ['decisions_description', 'value'],
      'decisions_description.format' => ['decisions_description', 'format'],
      'meeting_calendar_description.value' => ['meeting_calendar_description', 'value'],
      'meeting_calendar_description.format' => ['meeting_calendar_description', 'format'],
      'decision_search_description.value' => ['decision_search_description', 'value'],
      'decision_search_description.format' => ['decision_search_description', 'format'],
      'policymakers_search_description.value' => ['policymakers_search_description', 'value'],
      'policymakers_search_description.format' => ['policymakers_search_description', 'format'],
      'banner_heading' => ['banner_heading'],
      'banner_text.value' => ['banner_text', 'value'],
      'banner_text.format' => ['banner_text', 'format'],
      'banner_label' => ['banner_label'],
      'banner_url' => ['banner_url'],
    ];

    $values = $form_state->getValues();
    foreach ($assertions as $config_key => $value) {
      $expected = $config->get($config_key);
      $actual = $values;

      foreach ($value as $key) {
        $actual = $actual[$key];
      }

      $this->assertEquals($expected, $actual, "Failed asserting for config key: $config_key");
    }
  }

}
