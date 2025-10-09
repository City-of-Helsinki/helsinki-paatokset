<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_search\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\paatokset_search\SearchManager;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\node\Entity\NodeType;
use Drupal\user\RoleInterface;

/**
 * Tests search manager.
 *
 * @coversDefaultClass \Drupal\paatokset_search\SearchManager
 */
class SearchManagerTest extends EntityKernelTestBase {

  use NodeCreationTrait {
    createNode as drupalCreateNode;
  }

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'paatokset_search',
    'node',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $configSchemaCheckerExclusions = [
    'elastic_proxy.settings',
    'paatokset_search.settings',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['system']);
    $this->installSchema('node', ['node_access']);

    // Clear permissions for authenticated users.
    $this->config('user.role.' . RoleInterface::AUTHENTICATED_ID)
      ->set('permissions', [])
      ->save();
    // Create user 1 who has special permissions.
    $this->drupalCreateUser();

    $user = $this->drupalCreateUser([
      'access content',
    ]);
    $this->container->get('current_user')->setAccount($user);

    NodeType::create([
      'name' => $this->randomMachineName(),
      'type' => 'page',
    ]);
  }

  /**
   * Tests search manager build with defaults.
   *
   * @covers ::__construct
   * @covers ::build
   * @covers ::getOperatorGuideUrl
   */
  public function testBuildWithDefaults(): void {
    $this->setConfiguration([
      'elastic_proxy_url' => 'https://example.com',
    ], [
      'sentry_dsn_react' => 'https://sentry.example.com',
    ]);
    $manager = $this->container->get(SearchManager::class);

    $build = $manager->build('decisions', ['test-class']);

    $this->assertContains('hdbt_subtheme/decisions-search', $build['#attached']['library']);
    $this->assertEquals('https://sentry.example.com', $build['#attached']['drupalSettings']['paatokset_react_search']['sentry_dsn_react']);
    $this->assertEquals('decisions', $build['#search_element']['#attributes']['data-type']);
    $this->assertEquals('https://example.com', $build['#search_element']['#attributes']['data-url']);
    $this->assertContains('test-class', $build['#attributes']['class']);
  }

  /**
   * Tests search manager build with operator guide.
   *
   * @covers ::__construct
   * @covers ::build
   * @covers ::getOperatorGuideUrl
   */
  public function testBuildWithOperatorGuide(): void {
    $node = $this->drupalCreateNode([
      'type' => 'page',
      'title' => 'Test node',
    ]);
    $node->save();

    $this->setConfiguration([], [
      'operator_guide_node_id' => $node->id(),
    ]);
    $manager = $this->container->get(SearchManager::class);

    $build = $manager->build('policymakers');

    $this->assertEquals('/node/' . $node->id(), $build['#attributes']['data-operator-guide-url']);
  }

  /**
   * Tests search manager build with unpublished operator guide.
   *
   * @covers ::__construct
   * @covers ::build
   * @covers ::getOperatorGuideUrl
   */
  public function testBuildWithUnpublishedOperatorGuide(): void {
    $node = $this->drupalCreateNode([
      'type' => 'page',
      'title' => 'Test node',
      'uid' => '1',
      'status' => 0,
    ]);
    $node->save();

    $this->setConfiguration([], [
      'operator_guide_node_id' => $node->id(),
    ]);
    $manager = $this->container->get(SearchManager::class);

    $build = $manager->build('policymakers');

    $this->assertEmpty($build['#attributes']['data-operator-guide-url']);
  }

  /**
   * Helper function to set configuration.
   *
   * @param array $elastic_proxy
   *   The elastic proxy configuration.
   * @param array $paatokset_search
   *   The paatokset search configuration.
   */
  private function setConfiguration(array $elastic_proxy = [], array $paatokset_search = []): void {
    $elastic_proxy_config = $this->config('elastic_proxy.settings');
    foreach ($elastic_proxy as $key => $value) {
      $elastic_proxy_config->set($key, $value);
    }
    $elastic_proxy_config->save();

    $paatokset_search_config = $this->config('paatokset_search.settings');
    foreach ($paatokset_search as $key => $value) {
      $paatokset_search_config->set($key, $value);
    }
    $paatokset_search_config->save();
  }

}
