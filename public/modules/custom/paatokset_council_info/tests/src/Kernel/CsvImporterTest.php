<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_council_info\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\paatokset_council_info\Form\InfoImportForm;

/**
 * Tests items storage.
 *
 * @coversDefaultClass \Drupal\paatokset_council_info\Form\InfoImportForm
 */
class CsvImporterTest extends EntityKernelTestBase {

  /**
   * The messenger inteface.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected MessengerInterface $messenger;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'file',
    'system',
    'node',
    'user',
    'field',
    'text',
    'paatokset_council_info',
  ];

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['field', 'node', 'user', 'file']);

    $this->sut();
    $this->messenger = $this->container->get('messenger');
  }

  /**
   * Test the form functionality.
   */
  public function testFormFunctionality(): void {
    $form = InfoImportForm::create($this->container);
    $form_state = new FormState();

    $id = $form->getFormId();
    $formArray = $form->buildForm([], $form_state);

    $this->assertEquals('council_info_import', $id);
    $this->assertTrue(isset($formArray['file']));

    $this->assertNull(InfoImportForm::getFieldKey('SHOULD-RETURN-NULL'));
    $this->assertEquals('field_trustee_home_district', InfoImportForm::getFieldKey('Kotikaupunginosa'));
    $this->assertEquals('field_trustee_phone', InfoImportForm::getFieldKey('Puhelinnumero verkossa'));
    $this->assertEquals('field_trustee_email', InfoImportForm::getFieldKey('Sähköposti verkossa'));
    $this->assertEquals('field_trustee_homepage', InfoImportForm::getFieldKey('Kotisivu'));
    $this->assertEquals('field_trustee_profession', InfoImportForm::getFieldKey('Ammatti'));

    $file_id = $this->createFile();
    $form_state->setValue('info_file', [$file_id]);

    $formdata = [];
    $form->submitForm($formdata, $form_state);

    $data = $this->getData();
    $context['results'] = [
      'items' => [],
      'failed' => [],
    ];

    foreach ($data['items'] as $item) {
      InfoImportForm::doProcess($item, $context);
    }

    $this->assertEquals(count($context['results']['items']), 2, 'total items updated');
    $this->assertEquals(count($context['results']['failed']), 1, '1 item failed');

    $this->assertCount(0, $this->messenger->messagesByType(MessengerInterface::TYPE_STATUS));
    InfoImportForm::finishedCallback(TRUE, $context['results'], []);
    $this->assertCount(1, $this->messenger->messagesByType(MessengerInterface::TYPE_STATUS));
  }

  /**
   * Create nodes & bundle fields.
   */
  private function sut(): void {
    // Create a node type.
    NodeType::create([
      'type' => 'trustee',
      'name' => 'Trustee',
    ])->save();

    $fields_to_create = [
      'field_first_name',
      'field_last_name',
      'field_trustee_email',
    ];

    foreach ($fields_to_create as $fieldname) {
      // Create a field storage.
      FieldStorageConfig::create([
        'field_name' => $fieldname,
        'entity_type' => 'node',
        'type' => 'string',
        'cardinality' => 1,
        'settings' => [],
      ])->save();

      // Create a field instance.
      FieldConfig::create([
        'field_name' => $fieldname,
        'entity_type' => 'node',
        'bundle' => 'trustee',
        'label' => 'Trustee',
        'settings' => [],
      ])->save();
    }

    Node::create([
      'title' => 'title',
      'type' => 'trustee',
      'field_first_name' => 'test',
      'field_last_name' => 'testlastname',
    ])
      ->save();

    Node::create([
      'title' => 'title',
      'type' => 'trustee',
      'field_first_name' => 'test',
      'field_last_name' => 'testlastname2',
    ])
      ->save();
  }

  /**
   * Import data.
   *
   * @return array[]
   *   Array of data to process.
   */
  private function getData(): array {
    return [
      'items' => [
        [
          'count' => 1,
          'Etunimet' => 'test',
          'Sukunimi' => 'testlastname',
          'Sähköposti verkossa' => 'changed_email@email.com',
        ],
        [
          'count' => 2,
          'Etunimet' => 'test',
          'Sukunimi' => 'testlastname2',
          'Sähköposti verkossa' => 'changed_email222@email.com',
        ],
        [
          'count' => 3,
          'Etunimet' => 'doesnotexist',
          'Sukunimi' => 'testlastname3',
          'Sähköposti verkossa' => 'changed_email333@email.com',
        ],
      ],
    ];
  }

  /**
   * Create a csv file.
   *
   * @return int
   *   The file id.
   */
  private function createFile(): int {
    $file = File::create([
      'filename' => basename('test.csv'),
      'uri' => \Drupal::root() . '/modules/custom/paatokset_council_info/tests/fixtures/test.csv',
      'status' => 1,
      'uid' => 1,
    ]);
    $file->setTemporary();
    $file->save();
    return (int) $file->id();
  }

}
