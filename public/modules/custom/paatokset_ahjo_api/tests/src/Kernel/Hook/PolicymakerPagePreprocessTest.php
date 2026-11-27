<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\Hook;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\node\NodeInterface;
use Drupal\paatokset_ahjo_api\Entity\Policymaker;
use Drupal\Tests\paatokset_ahjo_api\Kernel\AhjoEntityKernelTestBase;
use Prophecy\Argument;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * Tests sidebar variables on policymaker pages.
 *
 * @see paatokset_policymakers_preprocess_page()
 */
class PolicymakerPagePreprocessTest extends AhjoEntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    // Provides the hide_sidebar_navigation field.
    'hdbt_admin_tools',
  ];

  /**
   * Tests the policymaker navigation sidebar.
   */
  public function testPolicymakerSidebar(): void {
    $policymaker = Policymaker::create([
      'title' => 'Kaupunginhallitus',
      'field_organization_type' => 'hallitus',
      'field_policymaker_id' => 'PM001',
    ]);
    $policymaker->save();

    $variables = $this->preprocess($policymaker);
    $this->assertTrue($variables['has_sidebar_first']);
    $this->assertTrue($variables['has_section_nav']);
    $this->assertArrayNotHasKey('hdbt_subtheme_main_navigation_level_2', $variables['page']['sidebar_first']);

    // The "Hide sidebar navigation" setting hides the sidebar.
    $hidden = Policymaker::create([
      'title' => 'Kaupunginvaltuusto',
      'field_organization_type' => 'valtuusto',
      'field_policymaker_id' => 'PM002',
      'hide_sidebar_navigation' => TRUE,
    ]);
    $hidden->save();

    $variables = $this->preprocess($hidden);
    $this->assertFalse($variables['has_sidebar_first']);
    $this->assertArrayNotHasKey('has_section_nav', $variables);
  }

  /**
   * Runs the page preprocess for the given node.
   *
   * @return array<string, mixed>
   *   The page variables.
   */
  private function preprocess(NodeInterface $node): array {
    $routeMatch = $this->prophesize(RouteMatchInterface::class);
    $routeMatch->getRouteName()->willReturn('entity.node.canonical');
    $routeMatch->getParameters()->willReturn(new ParameterBag(['node' => $node]));
    $routeMatch->getParameter(Argument::any())->willReturn(NULL);
    $routeMatch->getParameter('node')->willReturn($node);
    $this->container->set('current_route_match', $routeMatch->reveal());

    $variables = [
      'node' => $node,
      'has_sidebar_first' => FALSE,
      'page' => [
        'sidebar_first' => [
          'hdbt_subtheme_main_navigation_level_2' => [],
        ],
      ],
    ];
    paatokset_policymakers_preprocess_page($variables);

    return $variables;
  }

}
