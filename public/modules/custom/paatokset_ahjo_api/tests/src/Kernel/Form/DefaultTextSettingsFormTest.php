<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\Form;

use Drupal\Core\Form\FormState;
use Drupal\paatokset_ahjo_api\Form\DefaultTextSettingsForm;
use Drupal\Tests\paatokset_ahjo_api\Kernel\AhjoKernelTestBase;

/**
 * Tests the DefaultTextSettingsForm form.
 */
class DefaultTextSettingsFormTest extends AhjoKernelTestBase {

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

    // Simulate form submission values.
    $form_state->setValues([
      'calendar_notice_text' => 'Test content: calendar_notice_text',
      'committees_boards_url' => 'https://www.test.hel.ninja',
      'office_holders_url' => 'https://www.test.hel.ninja',
      'hidden_decisions_text' => [
        'value' => 'Test content: hidden_decisions_text',
        'format' => 'full_html',
      ],
      'non_public_attachments_text' => [
        'value' => 'Test content: non_public_attachments_text',
        'format' => 'full_html',
      ],
      'documents_description' => [
        'value' => 'Test content: documents_description',
        'format' => 'full_html',
      ],
      'meetings_description' => [
        'value' => 'Test content: meetings_description',
        'format' => 'full_html',
      ],
      'recording_description' => [
        'value' => 'Test content: recording_description',
        'format' => 'full_html',
      ],
      'decisions_description' => [
        'value' => 'Test content: decisions_description',
        'format' => 'full_html',
      ],
      'meeting_calendar_description' => [
        'value' => 'Test content: meeting_calendar_description',
        'format' => 'full_html',
      ],
      'decision_search_description' => [
        'value' => 'Test content: decision_search_description',
        'format' => 'full_html',
      ],
      'policymakers_search_description' => [
        'value' => 'Test content: policymakers_search_description',
        'format' => 'full_html',
      ],
      'banner_heading' => 'Test content: banner_heading',
      'banner_text' => [
        'value' => 'Test content: banner_text',
        'format' => 'full_html',
      ],
      'banner_label' => 'Test content: banner_label',
      'banner_url' => 'https://www.test.hel.ninja',
    ]);

    // Perform submit (assuming submit handler uses config saving).
    $form_object->submitForm($form_array, $form_state);

    // Get the saved values from configurations.
    $config_factory = $this->container->get('config.factory');
    $config = $config_factory->getEditable(DefaultTextSettingsForm::SETTINGS);

    // Assert that the config values are correctly saved.
    $values = $form_state->getValues();
    $this->assertEquals($config->get('calendar_notice_text'), $values['calendar_notice_text']);
    $this->assertEquals($config->get('committees_boards_url'), $values['committees_boards_url']);
    $this->assertEquals($config->get('office_holders_url'), $values['office_holders_url']);
    $this->assertEquals($config->get('hidden_decisions_text.value'), $values['hidden_decisions_text']['value']);
    $this->assertEquals($config->get('hidden_decisions_text.format'), $values['hidden_decisions_text']['format']);
    $this->assertEquals($config->get('non_public_attachments_text.value'), $values['non_public_attachments_text']['value']);
    $this->assertEquals($config->get('non_public_attachments_text.format'), $values['non_public_attachments_text']['format']);
    $this->assertEquals($config->get('documents_description.value'), $values['documents_description']['value']);
    $this->assertEquals($config->get('documents_description.format'), $values['documents_description']['format']);
    $this->assertEquals($config->get('meetings_description.value'), $values['meetings_description']['value']);
    $this->assertEquals($config->get('meetings_description.format'), $values['meetings_description']['format']);
    $this->assertEquals($config->get('recording_description.value'), $values['recording_description']['value']);
    $this->assertEquals($config->get('recording_description.format'), $values['recording_description']['format']);
    $this->assertEquals($config->get('decisions_description.value'), $values['decisions_description']['value']);
    $this->assertEquals($config->get('decisions_description.format'), $values['decisions_description']['format']);
    $this->assertEquals($config->get('meeting_calendar_description.value'), $values['meeting_calendar_description']['value']);
    $this->assertEquals($config->get('meeting_calendar_description.format'), $values['meeting_calendar_description']['format']);
    $this->assertEquals($config->get('decision_search_description.value'), $values['decision_search_description']['value']);
    $this->assertEquals($config->get('decision_search_description.format'), $values['decision_search_description']['format']);
    $this->assertEquals($config->get('policymakers_search_description.value'), $values['policymakers_search_description']['value']);
    $this->assertEquals($config->get('policymakers_search_description.format'), $values['policymakers_search_description']['format']);
    $this->assertEquals($config->get('banner_heading'), $values['banner_heading']);
    $this->assertEquals($config->get('banner_text.value'), $values['banner_text']['value']);
    $this->assertEquals($config->get('banner_text.format'), $values['banner_text']['format']);
    $this->assertEquals($config->get('banner_label'), $values['banner_label']);
    $this->assertEquals($config->get('banner_url'), $values['banner_url']);
  }

}
