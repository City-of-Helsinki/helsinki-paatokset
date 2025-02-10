<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_allu\Kernel;

use Drupal\helfi_api_base\Entity\RemoteEntityInterface;
use Drupal\paatokset_allu\Entity\Approval;
use Drupal\Tests\helfi_api_base\Kernel\Entity\Access\RemoteEntityAccessTestBase;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Kernel tests for document entity.
 */
class ApprovalEntityTest extends RemoteEntityAccessTestBase {

  use ProphecyTrait;
  use ApiTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'helfi_api_base',
    'paatokset_allu',
  ];

  /**
   * {@inheritDoc}
   */
  protected function setUpRemoteEntity(): RemoteEntityInterface {
    $this->installEntitySchema('paatokset_allu_approval');

    return Approval::create([
      'label' => 'test',
    ]);
  }

}
