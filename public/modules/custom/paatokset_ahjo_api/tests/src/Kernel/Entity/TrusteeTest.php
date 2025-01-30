<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\Entity;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\paatokset_ahjo_api\Entity\Trustee;
use Drupal\Tests\paatokset_ahjo_api\Kernel\AhjoKernelTestBase;

/**
 * Tests trustee bundle class.
 */
class TrusteeTest extends AhjoKernelTestBase {

  /**
   * Tests bundle class.
   */
  public function testBundleClass(): void {
    /** @var \Drupal\Core\Entity\EntityStorageInterface $storage */
    $storage = $this->container->get(EntityTypeManagerInterface::class)
      ->getStorage('node');

    $trustee = $storage->create([
      'type' => 'trustee',
      // The code expects names to be formatted 'Lastname, Firstname',
      // since that is how they are formatted in Ahjo API.
      'title' => 'Mehiläinen, Maija',
    ]);
    $this->assertInstanceOf(Trustee::class, $trustee);

    // Datapumppu can't handle commas.
    $this->assertEquals('Mehiläinen Maija', $trustee->getDatapumppuName());

    $trustee->set('field_trustee_datapumppu_id', 'test-override');

    // Manual override is used.
    $this->assertEquals('test-override', $trustee->getDatapumppuName());
  }

}
