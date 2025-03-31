<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\Entity;

use Drupal\helfi_api_base\Entity\RemoteEntityInterface;
use Drupal\paatokset_ahjo_api\Entity\Organization;
use Drupal\paatokset_ahjo_api\Plugin\migrate\source\AhjoOrganizationSource;
use Drupal\Tests\helfi_api_base\Kernel\Entity\Access\RemoteEntityAccessTestBase;

/**
 * Kernel tests for document entity.
 */
class OrganizationTest extends RemoteEntityAccessTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_api_base',
    'paatokset_ahjo_api',
  ];

  /**
   * {@inheritDoc}
   */
  protected function setUpRemoteEntity(): RemoteEntityInterface {
    $this->installEntitySchema('ahjo_organization');

    return Organization::create([
      'id' => AhjoOrganizationSource::ROOT_ORGANIZATION_ID,
      'label' => 'Root organization',
    ]);
  }

  /**
   * Tests organization structure.
   */
  public function testOrganizationStructure(): void {
    $root = $this->rmt;
    $child = Organization::create([
      'id' => '00400',
      'label' => 'Kaupunginhallitus',
      'organization_above' => $root,
    ]);
    $child->save();

    $this->assertInstanceOf(Organization::class, $root);
    $this->assertEquals($root->id(), $child->getParentOrganization()?->id());
    $this->assertEquals(NULL, $root->getParentOrganization());
  }

}
