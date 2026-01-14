<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\paatokset_ahjo_api\Service\CaseService;
use Drupal\paatokset_ahjo_api\Service\MeetingService;
use Drupal\paatokset_ahjo_api\Service\PolicymakerService;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\path_alias\Entity\PathAlias;
use Drupal\pathauto\AliasCleanerInterface;
use Drupal\Tests\paatokset_ahjo_api\Kernel\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for PolicymakerService.
 */
#[Group('paatokset_ahjo_api')]
class PolicymakerServiceTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'node',
    'language',
    'system',
    'text',
    'user',
  ];

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The policymaker service.
   */
  protected PolicymakerService $policymakerService;

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
    $this->policymakerService = $this->container->get(PolicymakerService::class);

    // Add languages used in the test.
    if (!ConfigurableLanguage::load('fi')) {
      ConfigurableLanguage::create(['id' => 'fi', 'label' => 'Finnish'])->save();
    }
    if (!ConfigurableLanguage::load('sv')) {
      ConfigurableLanguage::create(['id' => 'sv', 'label' => 'Swedish'])->save();
    }

    // Set default site language to Finnish.
    $this->config('system.site')
      ->set('default_langcode', 'fi')
      ->save();

    // Reset the language manager so current/default languages are recalculated.
    $this->container->get('language_manager')->reset();

    // If your routes are language-specific, rebuild the router.
    $this->container->get('router.builder')->rebuild();

    // Regrab the service after resets/rebuilds.
    $this->policymakerService = $this->container->get(PolicymakerService::class);
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
      $this->container->get(LanguageManagerInterface::class),
      $this->container->get(EntityTypeManagerInterface::class),
      $this->container->get(ConfigFactoryInterface::class),
      $route_match,
      $this->container->get(AliasManagerInterface::class),
      $this->container->get(AliasCleanerInterface::class),
      $this->container->get(MeetingService::class),
      $this->container->get(CaseService::class),
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
      $this->container->get(LanguageManagerInterface::class),
      $this->container->get(EntityTypeManagerInterface::class),
      $this->container->get(ConfigFactoryInterface::class),
      $route_match,
      $this->container->get(AliasManagerInterface::class),
      $this->container->get(AliasCleanerInterface::class),
      $this->container->get(MeetingService::class),
      $this->container->get(CaseService::class),
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

  /**
   * Test getting policymaker route.
   */
  public function testGetPolicymakerRoute(): void {
    // Create a test policymaker.
    $node = Node::create([
      'type' => 'policymaker',
      'title' => 'Test Policymaker',
      'field_policymaker_id' => 'test123',
      'status' => 1,
    ]);
    $node->save();

    $this->policymakerService->setPolicyMakerNode($node);

    // Default language should now be fi → route name should use fi.
    $route = $this->policymakerService->getPolicymakerRoute();
    $this->assertInstanceOf(Url::class, $route);
    $this->assertEquals('policymaker.page.fi', $route->getRouteName());

    // Explicit language sv (make sure 'sv' exists, we created it above).
    $route = $this->policymakerService->getPolicymakerRoute(NULL, 'sv');
    $this->assertInstanceOf(Url::class, $route);
    $this->assertEquals('policymaker.page.sv', $route->getRouteName());

    // Non-existent language should return NULL.
    $route = $this->policymakerService->getPolicymakerRoute(NULL, 'de');
    $this->assertNull($route);
  }

  /**
   * Test getting decision announcement.
   */
  public function testGetDecisionAnnouncement(): void {
    // Ensure the meeting node type exists.
    $node_type_storage = $this->container->get('entity_type.manager')->getStorage('node_type');
    if (!$node_type_storage->load('meeting')) {
      $node_type_storage->create([
        'type' => 'meeting',
        'name' => 'Meeting',
      ])->save();
    }

    // Create field storage and field if they don't exist.
    $field_storage = FieldStorageConfig::load('node.field_meeting_decision');
    if (!$field_storage) {
      $field_storage = FieldStorageConfig::create([
        'field_name' => 'field_meeting_decision',
        'entity_type' => 'node',
        'type' => 'text_long',
      ]);
      $field_storage->save();
    }

    $field = FieldConfig::load('node.meeting.field_meeting_decision');
    if (!$field) {
      $field = FieldConfig::create([
        'field_storage' => $field_storage,
        'bundle' => 'meeting',
        'label' => 'Meeting Decision',
      ]);
      $field->save();
    }

    // Create field_meeting_minutes_published if it doesn't exist.
    $minutes_field_storage = FieldStorageConfig::load('node.field_meeting_minutes_published');
    if (!$minutes_field_storage) {
      $minutes_field_storage = FieldStorageConfig::create([
        'field_name' => 'field_meeting_minutes_published',
        'entity_type' => 'node',
        'type' => 'boolean',
      ]);
      $minutes_field_storage->save();
    }

    $minutes_field = FieldConfig::load('node.meeting.field_meeting_minutes_published');
    if (!$minutes_field) {
      $minutes_field = FieldConfig::create([
        'field_storage' => $minutes_field_storage,
        'bundle' => 'meeting',
        'label' => 'Meeting Minutes Published',
      ]);
      $minutes_field->save();
    }

    // Create a test meeting node with decision announcement.
    $meeting = Node::create([
      'type' => 'meeting',
      'title' => 'Test Meeting',
      'field_meeting_decision' => [
        'value' => '
        <div class="Paattaja">Test Decision</div>
        <div class="Kokous">Meeting details</div>
        <div class="Paikka Paivamaara">Location and date</div>
        <div class="Tiedote">Announcement content</div>
      ',
        'format' => 'full_html',
      ],
      'field_meeting_minutes_published' => 0,
    ]);
    $meeting->save();

    $reflection = new \ReflectionClass(get_class($this->policymakerService));
    $method = $reflection->getMethod('getDecisionAnnouncement');

    // Test with valid meeting and langcode.
    $result = $method->invokeArgs($this->policymakerService, [$meeting, 'fi', []]);
    $this->assertIsArray($result);
    $this->assertArrayHasKey('heading', $result);
    $this->assertArrayHasKey('metadata', $result);
    $this->assertArrayHasKey('more_info', $result);
    $this->assertArrayHasKey('accordions', $result);

    // Test with published minutes, should return null.
    $meeting->set('field_meeting_minutes_published', 1);
    $result = $method->invokeArgs($this->policymakerService, [$meeting, 'fi', []]);
    $this->assertNull($result);

    // Test with empty decision field.
    $meeting->set('field_meeting_decision', []);
    $result = $method->invokeArgs($this->policymakerService, [$meeting, 'fi', []]);
    $this->assertNull($result);
  }

}
