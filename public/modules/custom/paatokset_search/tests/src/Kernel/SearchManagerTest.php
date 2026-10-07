<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_search\Kernel;

use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\paatokset_search\SearchManager;
use Drupal\Tests\helfi_api_base\Traits\EnvironmentResolverTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\node\Entity\NodeType;
use Drupal\user\RoleInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests search manager.
 */
#[RunTestsInSeparateProcesses]
#[Group('paatokset_search')]
class SearchManagerTest extends EntityKernelTestBase {

  use EnvironmentResolverTrait;
  use NodeCreationTrait {
    createNode as drupalCreateNode;
  }

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_api_base',
    'helfi_react_search',
    'paatokset_search',
    'node',
    'user',
    'diff',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['system', 'user']);
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
   */
  public function testBuildWithDefaults(): void {
    $this->setActiveProject(Project::PAATOKSET, EnvironmentEnum::Local);
    $this->setConfiguration([
      'sentry_dsn_react' => 'https://sentry.example.com',
    ]);
    $manager = $this->container->get(SearchManager::class);

    $build = $manager->build('decisions', ['test-class']);

    $this->assertContains('hdbt_subtheme/decisions-search', $build['#attached']['library']);
    $this->assertEquals('https://sentry.example.com', $build['#attached']['drupalSettings']['paatokset_react_search']['sentry_dsn_react']);
    $this->assertEquals('decisions', $build['#search_element']['#attributes']['data-type']);
    $this->assertEquals('https://elastic-proxy-helsinki-paatokset.docker.so', $build['#search_element']['#attributes']['data-url']);
    $this->assertContains('test-class', $build['#attributes']['class']);
  }

  /**
   * Tests search manager build with operator guide.
   */
  public function testBuildWithOperatorGuide(): void {
    $node = $this->drupalCreateNode([
      'type' => 'page',
      'title' => 'Test node',
    ]);
    $node->save();

    $this->setConfiguration([
      'operator_guide_node_id' => $node->id(),
    ]);
    $manager = $this->container->get(SearchManager::class);

    $url = $manager->getOperatorGuideUrl();

    $this->assertEquals('/node/' . $node->id(), $url);
  }

  /**
   * Tests search manager build with unpublished operator guide.
   */
  public function testBuildWithUnpublishedOperatorGuide(): void {
    $node = $this->drupalCreateNode([
      'type' => 'page',
      'title' => 'Test node',
      'uid' => '1',
      'status' => 0,
    ]);
    $node->save();

    $this->setConfiguration([
      'operator_guide_node_id' => $node->id(),
    ]);
    $manager = $this->container->get(SearchManager::class);

    $url = $manager->getOperatorGuideUrl();

    $this->assertEmpty($url);
  }

  /**
   * Helper function to set configuration.
   *
   * @param array $paatokset_search
   *   The paatokset search configuration.
   */
  private function setConfiguration(array $paatokset_search = []): void {
    $paatokset_search_config = $this->config('paatokset_search.settings');
    foreach ($paatokset_search as $key => $value) {
      $paatokset_search_config->set($key, $value);
    }
    $paatokset_search_config->save();
  }

}
