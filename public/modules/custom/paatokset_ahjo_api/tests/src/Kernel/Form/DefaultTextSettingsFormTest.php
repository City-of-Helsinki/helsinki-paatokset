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

    // Build and process the form.
    /** @var \Drupal\Core\Form\FormBuilderInterface $form_builder */
    $form_builder = $this->container->get('form_builder');
    $form_id = $form_builder->getFormId($form_object, $form_state);
    $form = $form_builder->retrieveForm($form_id, $form_state);
    $form_builder->prepareForm($form_id, $form, $form_state);
    $form_builder->processForm($form_id, $form, $form_state);

    // Simulate form submission values.
    $form_state->setUserInput([
      'calendar_notice_text' => 'Test content: calendar_notice_text',
      'committees_boards_url' => 'https://www.test.hel.ninja',
      'office_holders_url' => 'https://www.test.hel.ninja',
      'hidden_decisions_text[value]' => 'Test content: hidden_decisions_text',
      'hidden_decisions_text[format]' => 'full_html',
    ]);
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
    $form_builder->submitForm($form_object, $form_state);

    // Get the saved values from configurations.
    $config_factory = $this->container->get('config.factory');
    $config = $config_factory->getEditable(DefaultTextSettingsForm::SETTINGS);

    // Assert that the config values are correctly saved.
    $user_input = $form_state->getUserInput();
    $this->assertEquals($config->get('calendar_notice_text'), $user_input['calendar_notice_text']);
    $this->assertEquals($config->get('committees_boards_url'), $user_input['committees_boards_url']);
    $this->assertEquals($config->get('office_holders_url'), $user_input['office_holders_url']);
    $this->assertEquals($config->get('hidden_decisions_text.value'), $user_input['hidden_decisions_text']['value']);
    $this->assertEquals($config->get('hidden_decisions_text.format'), $user_input['hidden_decisions_text']['format']);
    $this->assertEquals($config->get('non_public_attachments_text.value'), $user_input['non_public_attachments_text']['value']);
    $this->assertEquals($config->get('non_public_attachments_text.format'), $user_input['non_public_attachments_text']['format']);
    $this->assertEquals($config->get('documents_description.value'), $user_input['documents_description']['value']);
    $this->assertEquals($config->get('documents_description.format'), $user_input['documents_description']['format']);
    $this->assertEquals($config->get('meetings_description.value'), $user_input['meetings_description']['value']);
    $this->assertEquals($config->get('meetings_description.format'), $user_input['meetings_description']['format']);
    $this->assertEquals($config->get('recording_description.value'), $user_input['recording_description']['value']);
    $this->assertEquals($config->get('recording_description.format'), $user_input['recording_description']['format']);
    $this->assertEquals($config->get('decisions_description.value'), $user_input['decisions_description']['value']);
    $this->assertEquals($config->get('decisions_description.format'), $user_input['decisions_description']['format']);
    $this->assertEquals($config->get('meeting_calendar_description.value'), $user_input['meeting_calendar_description']['value']);
    $this->assertEquals($config->get('meeting_calendar_description.format'), $user_input['meeting_calendar_description']['format']);
    $this->assertEquals($config->get('decision_search_description.value'), $user_input['decision_search_description']['value']);
    $this->assertEquals($config->get('decision_search_description.format'), $user_input['decision_search_description']['format']);
    $this->assertEquals($config->get('policymakers_search_description.value'), $user_input['policymakers_search_description']['value']);
    $this->assertEquals($config->get('policymakers_search_description.format'), $user_input['policymakers_search_description']['format']);
    $this->assertEquals($config->get('banner_heading'), $user_input['banner_heading']);
    $this->assertEquals($config->get('banner_text.value'), $user_input['banner_text']['value']);
    $this->assertEquals($config->get('banner_text.format'), $user_input['banner_text']['format']);
    $this->assertEquals($config->get('banner_label'), $user_input['banner_label']);
    $this->assertEquals($config->get('banner_url'), $user_input['banner_url']);
  }

}
