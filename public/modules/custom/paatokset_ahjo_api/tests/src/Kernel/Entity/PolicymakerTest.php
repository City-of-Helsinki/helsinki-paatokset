<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\Entity;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\paatokset_ahjo_api\Entity\Policymaker;
use Drupal\Tests\paatokset_ahjo_api\Kernel\AhjoKernelTestBase;

/**
 * Tests policymaker bundle class.
 */
class PolicymakerTest extends AhjoKernelTestBase {

  /**
   * Tests bundle class.
   */
  public function testBundleClass(): void {
    /** @var \Drupal\Core\Entity\EntityStorageInterface $storage */
    $storage = $this->container->get(EntityTypeManagerInterface::class)
      ->getStorage('node');

    $policymaker = $storage->create([
      'type' => 'policymaker',
      'status' => '1',
      'langcode' => 'en',
      'title' => 'Test policymaker',
      'field_policymaker_id' => '123',
    ]);

    $this->assertInstanceOf(Policymaker::class, $policymaker);

    $policymaker->set('field_ahjo_title', 'Test override');
    $this->assertEquals('Test policymaker', $policymaker->getPolicymakerName());
    $this->assertEquals('Test override', $policymaker->getPolicymakerName(TRUE));

    $policymaker->set('field_policymaker_existing', '1');
    $this->assertTrue($policymaker->isActive());

    $policymaker->set('field_policymaker_existing', '0');
    $this->assertTrue(!$policymaker->isActive());

    $this->assertEquals('color-none', $policymaker->getPolicymakerClass());
    $policymaker->set('field_organization_type', 'luottamushenkilö');
    $this->assertEquals('color-engel', $policymaker->getPolicymakerClass());
    $policymaker->set('field_city_council_division', '123');
    $this->assertEquals('color-hopea', $policymaker->getPolicymakerClass());
    $policymaker->set('field_organization_color_code', 'override');
    $this->assertEquals('override', $policymaker->getPolicymakerClass());

    $this->assertEmpty($policymaker->getDecisionsRoute('fi'));

    $this->installEntitySchema('ahjo_organization');
    $this->assertEmpty($policymaker->getOrganization());
    $this->container
      ->get(EntityTypeManagerInterface::class)
      ->getStorage('ahjo_organization')
      ->create([
        'id' => '123',
        'label' => 'Test organization',
      ])
      ->save();
    $this->assertNotEmpty($policymaker->getOrganization());
  }

}
