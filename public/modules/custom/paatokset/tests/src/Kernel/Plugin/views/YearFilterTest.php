<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset\Kernel\Plugin\views;

use Drupal\Core\Form\FormState;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\paatokset\Plugin\views\filter\YearFilter;
use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\views\Tests\ViewTestData;
use Drupal\views\Views;

/**
 * Tests year filter plugin.
 *
 * @coversDefaultClass \Drupal\paatokset\Plugin\views\filter\YearFilter
 * @group paatokset
 */
class YearFilterTest extends ViewsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'serialization',
    'system',
    'user',
    'node',
    'field',
    'paatokset',
    'paatokset_test_views',
  ];

  /**
   * {@inheritdoc}
   */
  public static $testViews = ['test_year_filter'];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE): void {
    parent::setUp(FALSE);

    ViewTestData::createTestViews(static::class, ['paatokset_test_views']);

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);

    // Create node type.
    $node_type = NodeType::create([
      'type' => 'test_content',
      'name' => 'Test Content',
    ]);
    $node_type->save();

    // Create test nodes with different years.
    $this->createTestNodes();
  }

  /**
   * Tests filter query.
   */
  public function testQuery(): void {
    $view = Views::getView('test_year_filter');
    $view->setDisplay();

    $this->executeView($view);
    $this->assertCount(2, $view->result);

    $view->destroy();
    $view->setDisplay();

    // Add filter value.
    $view->displayHandlers->get('default')->overrideOption('filters', [
      'created_year' => [
        'id' => 'created_year',
        'field' => 'created_year',
        'table' => 'node_field_data',
        'value' => [2024],
        'operator' => '=',
      ],
    ]);

    $this->executeView($view);
    $this->assertCount(1, $view->result);
  }

  /**
   * Tests options query.
   */
  public function testOptions(): void {
    $view = Views::getView('test_year_filter');
    $view->setDisplay();

    // Expose filter.
    $view->displayHandlers->get('default')->overrideOption('filters', [
      'created_year' => [
        'id' => 'created_year',
        'field' => 'created_year',
        'table' => 'node_field_data',
        'operator' => '=',
        'exposed' => TRUE,
        'expose' => [
          'identifier' => 'year',
        ],
      ],
    ]);

    $view->initHandlers();

    $filter = $view->filter['created_year'];
    $this->assertInstanceOf(YearFilter::class, $filter);

    $formState = new FormState();
    $form = [];
    $filter->buildExposedForm($form, $formState);

    $this->assertEquals('helfi_select', $form['year']['#type'] ?? '');
    $this->assertEquals([
      '2023' => '2023',
      '2024' => '2024',
    ], $form['year']['#options'] ?? []);
  }

  /**
   * Creates nodes for testing.
   */
  private function createTestNodes(): void {
    // Create nodes from 2023 and 2024.
    $dates = [
      mktime(0, 0, 0, 6, 15, 2023),
      mktime(0, 0, 0, 8, 20, 2024),
    ];

    foreach ($dates as $timestamp) {
      $node = Node::create([
        'type' => 'test_content',
        'title' => 'Test Node ' . date('Y', $timestamp),
        'status' => 1,
        'created' => $timestamp,
      ]);
      $node->save();
    }
  }

}
