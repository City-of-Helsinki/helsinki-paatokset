<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\AhjoAdmin;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\paatokset_ahjo_api\AhjoAdmin\Form\DefaultTextSettingsForm;
use Drupal\Tests\paatokset_ahjo_api\Kernel\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the DefaultTextSettingsForm form.
 */
#[RunTestsInSeparateProcesses]
#[Group('paatokset_ahjo_api')]
class DefaultTextSettingsFormTest extends KernelTestBase {

  // Fields with 'value' and 'format' structure.
  const array RICH_TEXT_FIELDS = [
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
  const array SIMPLE_TEXT_FIELDS = [
    'calendar_notice_text',
    'banner_heading',
    'banner_label',
  ];

  // Fields with URL values.
  const array URL_FIELDS = [
    'committees_boards_url',
    'office_holders_url',
    'banner_url',
  ];

  /**
   * Tests form submission.
   */
  public function testFormSubmission(): void {
    $form_object = DefaultTextSettingsForm::create($this->container);
    $form_state = new FormState();
    $form_array = $this->container
      ->get(FormBuilderInterface::class)
      ->buildForm($form_object, $form_state);

    // Check that some expected elements exist.
    $this->assertArrayHasKey('links', $form_array);
    $this->assertArrayHasKey('alerts', $form_array);
    $this->assertArrayHasKey('defaults', $form_array);
    $this->assertArrayHasKey('banner', $form_array);

    // Assert that the config values are correctly saved.
    $assertions = [];
    $form_values = [];

    // Create rich text fields.
    foreach (self::RICH_TEXT_FIELDS as $field) {
      $form_values[$field] = [
        'value' => "Test content: $field",
        'format' => 'full_html',
      ];

      $assertions["$field.value"] = [$field, 'value'];
      $assertions["$field.format"] = [$field, 'format'];
    }

    // Create simple text fields.
    foreach (self::SIMPLE_TEXT_FIELDS as $field) {
      $form_values[$field] = "Test content: $field";

      $assertions[$field] = [$field];
    }

    // Create URL fields.
    foreach (self::URL_FIELDS as $field) {
      $form_values[$field] = 'https://www.test.hel.ninja';

      $assertions[$field] = [$field];
    }

    // Perform submit.
    $form_state->setValues($form_values);
    $form_object->submitForm($form_array, $form_state);

    $config = $this->container
      ->get(ConfigFactoryInterface::class)
      ->get(array_first($form_object->getEditableConfigNames()));

    // Assert that saved config matches submitted values.
    foreach ($assertions as $config_key => $valuePath) {
      $actual = $config->get($config_key);

      // Find the submitted value for assertion from submitted values.
      // The assertions array contains array path to follow.
      $expected = array_reduce($valuePath, static fn ($carry, $item) => $carry[$item], $form_values);

      $this->assertEquals($expected, $actual, "Failed asserting for config key: $config_key");
    }
  }

}
