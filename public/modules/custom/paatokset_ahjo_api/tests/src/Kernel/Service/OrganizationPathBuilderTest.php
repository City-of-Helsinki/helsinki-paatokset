<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\paatokset_ahjo_api\Entity\Policymaker;
use Drupal\paatokset_ahjo_api\Service\OrganizationPathBuilder;
use Drupal\Tests\paatokset_ahjo_api\Kernel\AhjoEntityKernelTestBase;

/**
 * Tests case controller.
 */
class OrganizationPathBuilderTest extends AhjoEntityKernelTestBase {

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('ahjo_organization');
  }

  /**
   * Tests case controller.
   */
  public function testOrganizationPathBuilder(): void {
    /** @var \Drupal\Core\Entity\EntityStorageInterface $storage */
    $storage = $this->container->get(EntityTypeManagerInterface::class)
      ->getStorage('ahjo_organization');

    $storage->create([
      'id' => '00001',
      'title' => 'Helsingin kaupunki',
      'langcode' => 'fi',
    ])->save();
    $storage->create([
      'id' => '02900',
      'title' => 'Kaupunginvaltuusto',
      'organization_above' => '00001',
      'langcode' => 'fi',
    ])->save();
    $storage->create([
      'id' => '00400',
      'title' => 'Kaupunginhallitus',
      'organization_above' => '02900',
      'langcode' => 'fi',
    ])->save();

    /** @var \Drupal\Core\Entity\EntityStorageInterface $storage */
    $storage = $this->container->get(EntityTypeManagerInterface::class)
      ->getStorage('node');

    $policymaker = $storage->create([
      'type' => 'policymaker',
      'status' => '1',
      'langcode' => 'fi',
      'title' => 'Test policymaker',
      'field_policymaker_id' => '00400',
    ]);

    $this->assertInstanceOf(Policymaker::class, $policymaker);

    $sut = new OrganizationPathBuilder();
    $this->assertEquals([
      [
        'title' => 'Kaupunginvaltuusto',
        'langcode' => 'fi',
      ],
      [
        'title' => 'Kaupunginhallitus',
        'langcode' => 'fi',
      ],
    ], $sut->build($policymaker)['#organizations'] ?? NULL);

    $policymaker->set('field_policymaker_id', '02900');
    $this->assertEmpty($sut->build($policymaker));

    $policymaker->set('field_policymaker_id', '00001');
    $this->assertEmpty($sut->build($policymaker));

    $policymaker->set('field_policymaker_id', 'invalid-value');
    $this->assertEmpty($sut->build($policymaker));
  }

}
