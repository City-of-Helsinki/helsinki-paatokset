<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\AhjoAdmin;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\NodeForm;
use Drupal\paatokset_ahjo_api\AhjoAdmin\AdminHooks;
use Drupal\paatokset_ahjo_api\Entity\AhjoUpdatableInterface;
use Drupal\Tests\helfi_api_base\Traits\EnvironmentResolverTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests admin hooks.
 */
class AdminHooksTest extends KernelTestBase {

  use EnvironmentResolverTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'helfi_api_base',
    'paatokset_ahjo_api',
    'paatokset_ahjo_proxy',
    'migrate',
    'file',
    'paatokset_ahjo_openid',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');

    // Create uid 1 account.
    $this->createUser();
  }

  /**
   * Test permissions.
   */
  public function testAccess(): void {
    $environmentResolver = $this->getEnvironmentResolver('paatokset', 'prod');

    $sut = new AdminHooks($this->container->get('current_user'), $environmentResolver);

    // Form entity.
    $entity = $this->prophesize(AhjoUpdatableInterface::class);
    $entity
      ->getProxyUrl()
      ->willReturn(Url::fromRoute('<front>'));
    $entity
      ->id()
      ->willReturn('1');
    $entity
      ->isNew()
      ->willReturn(FALSE);

    // Entity form.
    $form = $this->prophesize(NodeForm::class);
    $form->getEntity()
      ->willReturn($entity->reveal());

    // Form state.
    $formState = $this->prophesize(FormStateInterface::class);
    $formState->getFormObject()->willReturn($form->reveal());

    $user = $this->createUser();
    $this->setCurrentUser($user);

    $build = [];
    $sut->alterForm($build, $formState->reveal());

    // User with no permissions does not see ahjo admin controls.
    $this->assertEmpty($build['ahjo'] ?? []);

    $user = $this->createUser(['access ahjo proxy']);
    $this->setCurrentUser($user);

    $build = [];
    $sut->alterForm($build, $formState->reveal());

    // User with access to ahjo proxy sees view links.
    $this->assertNotEmpty($build['ahjo']['actions']['#links']['view'] ?? NULL);

    $user = $this->createUser(['administer paatokset']);
    $this->setCurrentUser($user);

    $build = [];
    $sut->alterForm($build, $formState->reveal());

    // User with admin acecss sees update links.
    $this->assertNotEmpty($build['ahjo']['actions']['#links']['update'] ?? NULL);
  }

}
