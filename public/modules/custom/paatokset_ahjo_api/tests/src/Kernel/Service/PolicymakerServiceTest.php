<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\Service;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\paatokset_policymakers\Service\PolicymakerService;
use Drupal\path_alias\Entity\PathAlias;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests for PolicymakerService.
 *
 * @group paatokset_ahjo_api
 * @coversDefaultClass \Drupal\paatokset_policymakers\Service\PolicymakerService
 */
class PolicymakerServiceTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'helfi_api_base',
    'node',
    'paatokset_ahjo_api',
    'paatokset_policymakers',
    'path_alias',
    'pathauto',
    'system',
    'text',
    'token',
    'user',
  ];

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The policymaker service.
   *
   * @var \Drupal\paatokset_policymakers\Service\PolicymakerService
   */
  protected $policymakerService;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('path_alias');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('user', ['users_data']);
    $this->installConfig([
      'system',
      'node',
      'path_alias',
      'text',
      'field',
    ]);
    $this->entityTypeManager = $this->container->get('entity_type.manager');

    // Create the body field storage.
    if (!FieldStorageConfig::load('node.body')) {
      FieldStorageConfig::create([
        'field_name' => 'body',
        'entity_type' => 'node',
        'type' => 'text_with_summary',
        'entity_types' => ['node'],
      ])->save();
    }

    // Ensure the Policymaker content type exists.
    $node_type_storage = $this->entityTypeManager->getStorage('node_type');
    if (!$node_type_storage->load('policymaker')) {
      $node_type_storage->create([
        'type' => 'policymaker',
        'name' => 'Policymaker',
        'description' => 'Policymaker content type',
      ])->save();
    }

    // Create the Policymaker ID field storage.
    $field_storage = FieldStorageConfig::load('node.field_policymaker_id');
    if (!$field_storage) {
      $field_storage = FieldStorageConfig::create([
        'field_name' => 'field_policymaker_id',
        'entity_type' => 'node',
        'type' => 'string',
        'cardinality' => 1,
      ]);
      $field_storage->save();

      // Add the field to the content type.
      $field = FieldConfig::create([
        'field_storage' => $field_storage,
        'bundle' => 'policymaker',
        'label' => 'Policymaker ID',
        'required' => TRUE,
      ]);
      $field->save();
    }

    // Create a test user with admin permissions.
    $admin_user = $this->createUser([], NULL, TRUE);
    $this->setCurrentUser($admin_user);

    // Get the services.
    $this->policymakerService = $this->container->get('paatokset_policymakers');
  }

  /**
   * Test empty query results.
   */
  public function testEmptyQueryResults(): void {
    // Test with non-existent Policymaker.
    $result = $this->policymakerService->query(['policymaker' => 'non-existent']);
    $this->assertEmpty($result, 'Query with non-existent Policymaker should return empty array');
  }

  /**
   * Test getting non-existent policymaker.
   */
  public function testGetNonExistentPolicymaker(): void {
    $policymaker = $this->policymakerService->getPolicyMaker('non-existent');
    $this->assertNull($policymaker, 'Non-existent policymaker should return NULL');
  }

  /**
   * Test setting and getting policymaker by ID.
   */
  public function testSetAndGetPolicymakerById(): void {
    // Create a test policymaker.
    $node = Node::create([
      'type' => 'policymaker',
      'title' => 'Test Policymaker',
      'field_policymaker_id' => 'test123',
      'status' => 1,
    ]);
    $node->save();

    // Test setting policymaker by ID.
    $this->policymakerService->setPolicyMaker('test123');
    $policymaker = $this->policymakerService->getPolicyMaker();
    $this->assertEquals('test123', $policymaker->get('field_policymaker_id')->value);
  }

  /**
   * Test setting policymaker by path.
   */
  public function testSetPolicymakerByPath(): void {
    // Create a test policymaker node.
    $node = Node::create([
      'type' => 'policymaker',
      'title' => 'Test Policymaker',
      'field_policymaker_id' => 'test123',
      'status' => 1,
    ]);
    $node->save();

    // Create a path alias for the node.
    $path_alias = PathAlias::create([
      'path' => '/node/' . $node->id(),
      'alias' => '/paattajat/test123',
      'langcode' => 'fi',
    ]);
    $path_alias->save();

    // Create a mock for the route parameters
    // Mock the route match service.
    $route_match = $this->getMockBuilder('Drupal\Core\Routing\RouteMatchInterface')
      ->disableOriginalConstructor()
      ->getMock();

    // Set up the route match to return the node when getParameter() is called.
    $route_match->expects($this->any())
      ->method('getParameter')
      ->with('node')
      ->willReturn($node);

    // Create a mock for the parameters.
    $parameters = $this->getMockBuilder('Drupal\Core\Routing\RouteMatch')
      ->disableOriginalConstructor()
      ->getMock();

    $route_match->expects($this->any())
      ->method('getParameters')
      ->willReturn($parameters);

    // Replace the route match service in the container.
    $this->container->set('current_route_match', $route_match);
    $this->container->set('path_alias.manager', $this->container->get('path_alias.manager'));

    // Create a new instance of the service with our mocks.
    $policymaker_service = new PolicymakerService(
      $this->container->get('language_manager'),
      $this->container->get('entity_type.manager'),
      $this->container->get('config.factory'),
      $route_match,
      $this->container->get('path_alias.manager'),
      $this->container->get('pathauto.alias_cleaner')
    );

    // Test setting policymaker by path.
    $result = $policymaker_service->setPolicyMakerByPath();
    $this->assertTrue($result, 'Setting policymaker by path should succeed');
    $policymaker = $policymaker_service->getPolicyMaker();
    $this->assertEquals('test123', $policymaker->get('field_policymaker_id')->value);
  }

  /**
   * Test setting policymaker by path with subpages.
   */
  public function testSetPolicymakerByPathWithSubpages(): void {
    // Create a test policymaker.
    $node = Node::create([
      'type' => 'policymaker',
      'title' => 'Test Policymaker',
      'field_policymaker_id' => 'test123',
      'status' => 1,
    ]);
    $node->save();

    // Create a path alias for the node.
    $path_alias = PathAlias::create([
      'path' => '/node/' . $node->id(),
      'alias' => '/paattajat/test123',
      'langcode' => 'fi',
    ]);
    $path_alias->save();

    // Mock the route match service for subpage detection.
    $route_match = $this->getMockBuilder('Drupal\Core\Routing\RouteMatchInterface')
      ->disableOriginalConstructor()
      ->getMock();

    // Mock getParameter to return a non-policymaker node first.
    $non_policymaker_node = $this->getMockBuilder('Drupal\node\NodeInterface')
      ->disableOriginalConstructor()
      ->getMock();
    $non_policymaker_node->method('bundle')->willReturn('page');

    $route_match->expects($this->any())
      ->method('getParameter')
      ->with('node')
      ->willReturn($non_policymaker_node);

    // Mock the parameters.
    $parameters = $this->getMockBuilder('Drupal\Core\Routing\RouteMatch')
      ->disableOriginalConstructor()
      ->getMock();

    $route_match->expects($this->any())
      ->method('getParameters')
      ->willReturn($parameters);

    // Mock the URL to simulate a subpage.
    $url = $this->getMockBuilder('Drupal\Core\Url')
      ->disableOriginalConstructor()
      ->getMock();
    $url->expects($this->any())
      ->method('toString')
      ->willReturn('/fi/paattajat/test123/subpage');

    // Mock the URL generator.
    $url_generator = $this->getMockBuilder('Drupal\Core\Routing\UrlGeneratorInterface')
      ->disableOriginalConstructor()
      ->getMock();
    $url_generator->expects($this->any())
      ->method('generateFromRoute')
      ->with('<current>')
      ->willReturn('/fi/paattajat/test123/subpage');

    // Mock the path alias manager to handle the path resolution.
    $path_alias_manager = $this->getMockBuilder('Drupal\path_alias\AliasManagerInterface')
      ->disableOriginalConstructor()
      ->getMock();

    // Return the path when getPathByAlias is called with '/paattajat/test123'.
    $path_alias_manager->expects($this->any())
      ->method('getPathByAlias')
      ->willReturnCallback(function ($path, $langcode = NULL) use ($node) {
        if ($path === '/paattajat/test123' || $path === '/fi/paattajat/test123') {
          return '/node/' . $node->id();
        }
        return $path;
      });

    // Replace the path alias manager in the container.
    $this->container->set('path_alias.manager', $path_alias_manager);

    // Ensure the node is properly loaded when requested.
    $node_storage = $this->getMockBuilder('Drupal\Core\Entity\EntityStorageInterface')
      ->disableOriginalConstructor()
      ->getMock();
    $node_storage->expects($this->any())
      ->method('load')
      ->with($node->id())
      ->willReturn($node);

    $entity_type_manager = $this->getMockBuilder('Drupal\Core\Entity\EntityTypeManagerInterface')
      ->disableOriginalConstructor()
      ->getMock();
    $entity_type_manager->expects($this->any())
      ->method('getStorage')
      ->with('node')
      ->willReturn($node_storage);

    $this->container->set('entity_type.manager', $entity_type_manager);

    // Replace services in the container.
    $this->container->set('url_generator', $url_generator);
    $this->container->set('current_route_match', $route_match);

    // Create a new instance of the service with the mocked dependencies.
    $policymaker_service = new PolicymakerService(
      $this->container->get('language_manager'),
      $this->container->get('entity_type.manager'),
      $this->container->get('config.factory'),
      $route_match,
      $this->container->get('path_alias.manager'),
      $this->container->get('pathauto.alias_cleaner')
    );

    // Test setting policymaker by path with subpages.
    $result = $policymaker_service->setPolicyMakerByPath();
    $this->assertTrue($result, 'Setting policymaker by path with subpages should succeed');

    // Debug output.
    if (!$result) {
      $current_node = $route_match->getParameter('node');
      echo "Current node type: " . ($current_node ? $current_node->bundle() : 'null') . "\n";
      echo "Current URL: " . $url_generator->generateFromRoute('<current>') . "\n";
      echo "Path alias manager getPathByAlias calls: " . print_r($path_alias_manager->getPathByAlias('/paattajat/test123'), TRUE) . "\n";
    }

    $policymaker = $policymaker_service->getPolicyMaker();
    $this->assertNotNull($policymaker, 'Policymaker should not be null');
    $this->assertEquals('test123', $policymaker->get('field_policymaker_id')->value);
  }

}
