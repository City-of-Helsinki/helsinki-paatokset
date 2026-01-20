<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\AhjoAdmin;

use Drupal\Core\Form\FormState;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\paatokset_ahjo_api\AhjoAdmin\UpdateForm;
use Drupal\paatokset_ahjo_api\Entity\Decision;
use Drupal\paatokset_ahjo_proxy\AhjoProxy;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\paatokset_ahjo_api\Kernel\AhjoEntityKernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests update form.
 */
class UpdateFormTest extends AhjoEntityKernelTestBase {

  use UserCreationTrait;
  use ApiTestTrait;

  /**
   * Tests update form permissions.
   */
  public function testUpdateFormAccess(): void {
    // Create uid 1 account.
    $this->createUser();

    $node = Decision::create([
      'type' => 'decision',
      'title' => 'Test decision',
      'field_decision_native_id' => '123',
    ]);
    $node->save();
    $url = Url::fromRoute('paatokset_ahjo_api.ahjo_update_form', ['node' => $node->id()]);

    $user = $this->createUser(['access content']);
    $this->setCurrentUser($user);
    $this->assertFalse($url->access($user));

    $user = $this->createUser(['access content', 'administer paatokset']);
    $this->assertTrue($url->access($user));
  }

  /**
   * Tests update form permissions.
   */
  public function testUpdateForm(): void {
    $node = Decision::create([
      'type' => 'decision',
      'title' => 'Test decision',
      'field_decision_native_id' => '123',
    ]);

    $routeMatch = $this->prophesize(RouteMatchInterface::class);
    $routeMatch
      ->getParameter('node')
      ->willReturn($node);
    $this->container->set('current_route_match', $routeMatch->reveal());

    $ahjoProxy = $this->prophesize(AhjoProxy::class);

    $sut = new UpdateForm($ahjoProxy->reveal());

    $formState = new FormState();
    $form = $sut->buildForm([], $formState);

    $this->assertEquals($node::getAhjoEndpoint(), $form['type']['#default_value']);
    $this->assertEquals($node->getAhjoId(), $form['id']['#default_value']);

    $formState->setValues([
      'type' => $node::getAhjoEndpoint(),
      'id' => $node->getAhjoId(),
    ]);

    $ahjoProxy
      ->migrateSingleEntity($node::getAhjoEndpoint(), $node->getAhjoId())
      ->shouldBeCalled();

    $sut->submitForm($form, $formState);
  }

}
