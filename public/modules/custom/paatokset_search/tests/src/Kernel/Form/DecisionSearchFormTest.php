<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_search\Kernel\Form;

use Drupal\Core\Form\FormBuilderInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\paatokset_search\Form\DecisionSearchForm;
use Drupal\paatokset_search\SearchManager;

/**
 * Tests the decision search form.
 */
class DecisionSearchFormTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_api_base',
    'config_rewrite',
    'helfi_platform_config',
    'paatokset_search',
    'system',
  ];

  /**
   * Tests form rendering.
   */
  public function testForm() {
    $manager = $this->prophesize(SearchManager::class);
    $manager->getOperatorGuideUrl()->willReturn('https://example.com/operator-guide-url');

    $this->container->set(SearchManager::class, $manager->reveal());

    $form = $this->container->get(FormBuilderInterface::class)->getForm(DecisionSearchForm::class);
    $markup = $this->render($form);

    $this->assertStringContainsString('data-paatokset-textfield-autocomplete', $markup);
    $this->assertStringContainsString('https://example.com/operator-guide-url', $markup);
  }

}
